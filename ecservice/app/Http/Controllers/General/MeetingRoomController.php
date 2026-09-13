<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MeetingRoom;
use App\Models\Message;
use App\Models\User;
use App\Http\Requests\MeetingRoomRequest;
use App\Http\Resources\MeetingRoomsResource;
use App\Models\System\System;
use App\Models\Log;
use Carbon\Carbon;

class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminMeetings = $user->can('admin_meetings');
        $parent = isHasRole(System::USER_TYPE_PARENT_ROLE_NAME);
        $specialistTeacherIds = [];
        $keyword = mb_ereg_replace(" ", "%", getFTS($request->q));
        
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        if($parent){
            $user->load(['scaseParent.teacher:id', 'scaseParent.specialists:id']);
            $teacherIds = $user->scaseParent->pluck('teacher.*.id')->flatten()->all();
            $specialistIds = $user->scaseParent->pluck('specialists.*.id')->flatten()->all();
            $specialistTeacherIds = array_merge($teacherIds, $specialistIds);
        }

        $perPage = resolvePerPage($request);

        $rooms = MeetingRoom::with('user')
        ->where('meeting_rooms.center_id', $center)
        ->when(
            !$isAdminMeetings && !$parent,
            fn ($q) => $q->where(function ($query) use ($user) {
                $query->where('created_by_id', $user->id);
                $query->orWhere('type', MeetingRoom::TYPE_ADMINISTRATIVE);
            })
        )
        ->when(
            !$isAdminMeetings && $parent,
            fn ($q) => $q->where(function ($query) use ($specialistTeacherIds) {
                $query->whereIn('created_by_id', $specialistTeacherIds);
                $query->orWhere('type', MeetingRoom::TYPE_GENERAL);
            })
        )
        ->when(
            $request->q,
            fn ($q) => $q->where(function ($query) use ($keyword) {
                $query->where('title', 'like',"%{$keyword}%")
                ->orWhere('title_local', 'like',"%{$keyword}%");
            })
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );

        $rooms = $rooms->paginate($perPage);

        return apiPaginateResponse($rooms, MeetingRoomsResource::collection($rooms));
    }

    public function show(Request $request, $room)
    {
        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }
        
        $room = MeetingRoom::with('user')->where('center_id', $center)->withTrashed()->find($room);
        if($room)
            $room = new MeetingRoomsResource($room);
        else
            $room = null;

        return apiResponse($room);
    }

    public function put(MeetingRoomRequest $request, $room = null){

        $user = auth()->user();
        $input = $request->validated();
        $input['created_by_id'] = $user->id;
        $input['center_id'] = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $input['center_id'] = $request->center_id;
        }

        if(!$input['center_id'])
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);

        $meeting = MeetingRoom::updateOrCreate(['id' => $room], $input);

        $type = 'add_meeting';
        if($room>0)
            $type = 'edit_meeting';

        Log::add($type, $meeting, $meeting->id, '');

        return success();
    }

    public function delete($id)
    {
        $record = MeetingRoom::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_meeting', $record, $record->id, '');
        return success();
    }

    public function restore($id)
    {
        $record = MeetingRoom::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_meeting', $record, $record->id, '');
        return success();
    }

    public function start_meeting_meesage(Request $request)
    {
        $message = Message::create([
            'type' => $request->routeIs('meeting.start_now') ? Message::SYS_START_NOW_MEETING : Message::SYS_START_LATER_MEETING,
            'content' => $request->message,
            'meeting_room_id' => $request->meeting_room_id,
            'user_id' => auth()->user()->id
        ]);

        if(!$request->routeIs('meeting.start_now')) {

            $content = json_decode($request->message, true);
            $day = Carbon::parse($content['date'])->locale("ar")->dayName;
            
            $whatsAppMessage = "سوف تكون لدينا محاضرة";
            $whatsAppMessage .= " يوم {$day}";
            $whatsAppMessage .= " {$content['date']}";
            $whatsAppMessage .= " الساعة {$content['time']}";
            $whatsAppMessage .= " يمكن الدخول عن طريق الضغط على زرار 'بدء محادثة أونلاين' او استخدم الرابط التالي:";
            $whatsAppMessage .= " {$content['url']}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        }
        else {

            $whatsAppMessage = "بدأ الاجتماع الآن يمكن الدخول عن طريق استخدم الرابط التالي:";
            $whatsAppMessage .= " {$request->message}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        }

        $roles = [];
        $meetingRoom = MeetingRoom::find($request->meeting_room_id);
        if($meetingRoom->type == MeetingRoom::TYPE_ADMINISTRATIVE) {
            $roles = [
                'parent'
            ];
        }
        else {
            $roles = [
                'teacher', 
                'social_specialist', 
                'occupational_specialist', 
                'pronunciation_speech_specialist', 
                'psychotherapist_specialist', 
                'physiotherapist_specialist'
            ];
        }

        $users = User::with('roles:id,name')
        ->whereHas('roles', function ($query) use($roles) {
            $query->whereIn('default_name', $roles);
        })->get();

        \Log::channel('whatsapp')->info('whatsApp Message');
        \Log::channel('whatsapp')->info('Message: '.$whatsAppMessage);
        \Log::channel('whatsapp')->info('URL: '.$url);

        sendWhatsAppMessage(config('motabaa.dev.phone'), $whatsAppMessage, $url);
        sendWhatsAppMessage(config('motabaa.dev.phone_2'), $whatsAppMessage, $url);

        $usersPhone = [];
        if(!config('app.debug')) {
            foreach ($users as $user) {
                if($user->phone) {
                    $usersPhone[] = $user->phone;
                    sendWhatsAppMessage($user->phone, $whatsAppMessage, $url);
                }
            }
        }
        \Log::channel('whatsapp')->info($usersPhone);
        $logData = [];
        $logData['message'] = $whatsAppMessage;
        $logData['url'] = $url;
        $logData['phones'] = implode(", ", $usersPhone);
        Log::add('start_meeting_message', $message, $message->id, json_encode($logData));
        return success();
    }
}
