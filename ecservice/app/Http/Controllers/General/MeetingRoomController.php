<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRoomRequest;
use App\Http\Resources\MeetingRoomsResource;
use App\Models\Log;
use App\Models\MeetingRoom;
use App\Models\Message;
use App\Models\User;
use App\Models\Term;
use App\Models\System\System;
use App\Support\MeetingRoomAccess;
use App\Support\MeetingRoomInvitationService;
use App\Support\MeetingRoomNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminMeetings = $user->can('admin_meetings');
        $parent = isHasRole(System::USER_TYPE_PARENT_ROLE_NAME);
        $keyword = mb_ereg_replace(' ', '%', getFTS($request->q));

        $center = Term::resolveCenterId($user, $request->center_id);

        $perPage = resolvePerPage($request);

        $rooms = MeetingRoom::with(['user', 'assignedParents:id,name,phone'])
            ->where('meeting_rooms.center_id', $center)
            ->when(
                ! $isAdminMeetings && ! $parent,
                fn ($q) => $q->where(function ($query) use ($user) {
                    $query->where('created_by_id', $user->id);
                    $query->orWhere('type', MeetingRoom::TYPE_ADMINISTRATIVE);
                })
            )
            ->when(
                ! $isAdminMeetings && $parent,
                fn ($q) => $q->where(function ($query) use ($user) {
                    $query->where(function ($publicAudience) {
                        $publicAudience->whereNull('audience_type')
                            ->orWhere('audience_type', MeetingRoom::AUDIENCE_GENERAL);
                    })
                        ->orWhere(function ($parentSpecific) use ($user) {
                            $parentSpecific->where('audience_type', MeetingRoom::AUDIENCE_PARENTS)
                                ->whereHas('assignedParents', fn ($p) => $p->where('users.id', $user->id));
                        });
                })
            )
            ->when(
                $request->q,
                fn ($q) => $q->where(function ($query) use ($keyword) {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('title_local', 'like', "%{$keyword}%");
                })
            )
            ->when(
                $request->status && $request->status == 'all',
                fn ($q) => $q->withTrashed()
            )
            ->when(
                $request->status && $request->status == 'inactive',
                fn ($q) => $q->onlyTrashed()
            );

        $rooms = $rooms->paginate($perPage);

        return apiPaginateResponse($rooms, MeetingRoomsResource::collection($rooms));
    }

    public function show(Request $request, $room)
    {
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $room = MeetingRoom::with(['user', 'assignedParents:id,name,phone'])
            ->when($center, fn ($q) => $q->where('center_id', $center))
            ->withTrashed()
            ->find($room);

        if (! $room || ! MeetingRoomAccess::userCanAccess($user, $room)) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        return apiResponse(new MeetingRoomsResource($room));
    }

    public function put(MeetingRoomRequest $request, $room = null)
    {
        $user = auth()->user();
        if (! $user->can('edit_meetings') && ! $user->can('admin_meetings')) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        $input = $request->validated();
        if (! $request->has('type')) {
            unset($input['type']);
        } elseif (! isset($input['type'])) {
            $input['type'] = MeetingRoom::TYPE_GENERAL;
        }
        $input['created_by_id'] = $user->id;
        $input['center_id'] = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $input['center_id'] = $request->center_id;
        }

        if (! $input['center_id']) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $parentIds = array_values(array_map('intval', $input['parent_ids'] ?? []));
        unset($input['parent_ids']);

        if ($input['audience_type'] === MeetingRoom::AUDIENCE_PARENTS) {
            $validation = MeetingRoomAccess::validateParentAssignments((int) $input['center_id'], $parentIds);
            if (! $validation['valid']) {
                return $validation['response'];
            }
        } else {
            $parentIds = [];
        }

        $roomId = (int) $room;
        $existing = $roomId > 0 ? MeetingRoom::where('center_id', $input['center_id'])->find($roomId) : null;
        if ($roomId > 0 && ! $existing) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Room not found')]]], 422);
        }

        $meeting = DB::transaction(function () use ($input, $roomId, $parentIds) {
            $meeting = MeetingRoom::updateOrCreate(['id' => $roomId > 0 ? $roomId : null], $input);
            $meeting->assignedParents()->sync($parentIds);

            if (\Illuminate\Support\Facades\Schema::hasTable('meeting_room_notified_users')) {
                DB::table('meeting_room_notified_users')
                    ->where('meeting_room_id', $meeting->id)
                    ->whereNotIn('user_id', $parentIds ?: [0])
                    ->delete();
            }

            if ($input['audience_type'] !== MeetingRoom::AUDIENCE_LINK_INVITE) {
                MeetingRoomInvitationService::revokeTokensForRoom($meeting);
                MeetingRoomInvitationService::clearGrantsForRoom($meeting);
            }

            return $meeting->fresh(['assignedParents']);
        });

        $type = $roomId > 0 ? 'edit_meeting' : 'add_meeting';
        Log::add($type, $meeting, $meeting->id, '');

        if ($meeting->audience_type === MeetingRoom::AUDIENCE_PARENTS) {
            MeetingRoomNotifier::syncForRoom($meeting);
        }

        $responseData = ['room_id' => $meeting->id];
        if ($meeting->audience_type === MeetingRoom::AUDIENCE_LINK_INVITE) {
            $plain = MeetingRoomInvitationService::ensureActivePlainToken($meeting);
            if ($plain) {
                $responseData['invitation_url'] = MeetingRoomInvitationService::buildInvitationUrl($plain);
            }
        }

        return success($responseData);
    }

    public function invitationLink(Request $request, $room)
    {
        $user = auth()->user();
        if (! $user->can('edit_meetings') && ! $user->can('admin_meetings')) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        $center = Term::resolveCenterId($user, $request->center_id);
        $roomModel = MeetingRoom::query()
            ->when($center, fn ($q) => $q->where('center_id', $center))
            ->find($room);

        if (! $roomModel || ! MeetingRoomAccess::isLinkInviteAudience($roomModel)) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Room not found')]]], 422);
        }

        $rotate = filter_var($request->query('rotate', false), FILTER_VALIDATE_BOOLEAN);
        if ($rotate) {
            $plain = MeetingRoomInvitationService::rotatePlainToken($roomModel);
            $url = $plain ? MeetingRoomInvitationService::buildInvitationUrl($plain) : null;
        } else {
            $plain = MeetingRoomInvitationService::ensureActivePlainToken($roomModel);
            if ($plain) {
                $url = MeetingRoomInvitationService::buildInvitationUrl($plain);
            } elseif (MeetingRoomInvitationService::roomHasActiveInvitationToken($roomModel)) {
                return response()->json([
                    'errors' => ['error' => [__('validation.meeting_rooms.Invitation already issued')]],
                    'requires_rotate' => true,
                ], 422);
            } else {
                $url = null;
            }
        }

        if (! $url) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Invitation unavailable')]]], 422);
        }

        return apiResponse(['invitation_url' => $url]);
    }

    public function invitationStatus(Request $request)
    {
        $token = (string) $request->query('token', '');
        $valid = MeetingRoomInvitationService::tokenIsValid($token);

        return apiResponse([
            'valid' => $valid,
            'requires_login' => ! auth()->check(),
        ]);
    }

    public function redeemInvitation(Request $request)
    {
        $request->validate([
            'token' => 'required|string|min:16|max:128',
        ]);

        $user = auth()->user();
        if (! $user) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Login required')]]], 401);
        }

        $result = MeetingRoomInvitationService::redeemForUser((string) $request->token, $user);
        if (! $result['ok']) {
            $message = match ($result['error']) {
                'center', 'role' => __('validation.meeting_rooms.Invitation not authorized'),
                default => __('validation.meeting_rooms.Invitation invalid'),
            };

            return response()->json(['errors' => ['error' => [$message]]], 403);
        }

        return apiResponse([
            'room_id' => $result['room']->id,
        ]);
    }

    public function delete($id)
    {
        $record = MeetingRoom::find($id);
        if (! $record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_meeting', $record, $record->id, '');

        return success();
    }

    public function restore($id)
    {
        $record = MeetingRoom::withTrashed()->find($id);
        if (! $record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_meeting', $record, $record->id, '');

        return success();
    }

    public function start_meeting_meesage(Request $request)
    {
        $user = auth()->user();
        $meetingRoom = MeetingRoom::find($request->meeting_room_id);
        if (! $meetingRoom || ! MeetingRoomAccess::userCanAccess($user, $meetingRoom)) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        $message = Message::create([
            'type' => $request->routeIs('meeting.start_now') ? Message::SYS_START_NOW_MEETING : Message::SYS_START_LATER_MEETING,
            'content' => $request->message,
            'meeting_room_id' => $request->meeting_room_id,
            'user_id' => auth()->user()->id,
        ]);

        if (! $request->routeIs('meeting.start_now')) {

            $content = json_decode($request->message, true);
            $day = Carbon::parse($content['date'])->locale('ar')->dayName;

            $whatsAppMessage = 'سوف تكون لدينا محاضرة';
            $whatsAppMessage .= " يوم {$day}";
            $whatsAppMessage .= " {$content['date']}";
            $whatsAppMessage .= " الساعة {$content['time']}";
            $whatsAppMessage .= " يمكن الدخول عن طريق الضغط على زرار 'بدء محادثة أونلاين' او استخدم الرابط التالي:";
            $whatsAppMessage .= " {$content['url']}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        } else {

            $whatsAppMessage = 'بدأ الاجتماع الآن يمكن الدخول عن طريق استخدم الرابط التالي:';
            $whatsAppMessage .= " {$request->message}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        }

        $users = $this->meetingNotificationRecipients($meetingRoom);

        \Log::channel('whatsapp')->info('whatsApp Message');
        \Log::channel('whatsapp')->info('Message: '.$whatsAppMessage);
        \Log::channel('whatsapp')->info('URL: '.$url);

        sendWhatsAppMessage(config('motabaa.dev.phone'), $whatsAppMessage, $url);
        sendWhatsAppMessage(config('motabaa.dev.phone_2'), $whatsAppMessage, $url);

        $usersPhone = [];
        if (! config('app.debug')) {
            foreach ($users as $notifyUser) {
                if ($notifyUser->phone) {
                    $usersPhone[] = $notifyUser->phone;
                    sendWhatsAppMessage($notifyUser->phone, $whatsAppMessage, $url);
                }
            }
        }
        \Log::channel('whatsapp')->info($usersPhone);
        $logData = [];
        $logData['message'] = $whatsAppMessage;
        $logData['url'] = $url;
        $logData['phones'] = implode(', ', $usersPhone);
        Log::add('start_meeting_message', $message, $message->id, json_encode($logData));

        return success();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function meetingNotificationRecipients(MeetingRoom $meetingRoom)
    {
        if (MeetingRoomAccess::isParentSpecificAudience($meetingRoom)) {
            return $meetingRoom->assignedParents()->get();
        }

        if (MeetingRoomAccess::isLinkInviteAudience($meetingRoom)) {
            if (! MeetingRoomInvitationService::tablesReady()) {
                return collect();
            }

            return User::query()
                ->whereIn('id', function ($q) use ($meetingRoom) {
                    $q->select('user_id')
                        ->from('meeting_room_link_grants')
                        ->where('meeting_room_id', $meetingRoom->id);
                })
                ->get();
        }

        if (MeetingRoomAccess::isPublicAudience($meetingRoom)) {
            return User::query()
                ->whereHas('centers', fn ($q) => $q->where('centers.id', $meetingRoom->center_id))
                ->whereHas('roles', fn ($q) => $q->where('default_name', System::USER_TYPE_PARENT_ROLE_NAME))
                ->get();
        }

        $roles = [];
        if ($meetingRoom->type == MeetingRoom::TYPE_ADMINISTRATIVE) {
            $roles = ['parent'];
        } else {
            $roles = [
                'teacher',
                'social_specialist',
                'occupational_specialist',
                'pronunciation_speech_specialist',
                'psychotherapist_specialist',
                'physiotherapist_specialist',
            ];
        }

        return User::with('roles:id,name')
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('default_name', $roles);
            })->get();
    }
}
