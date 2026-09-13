<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Goal;
use App\Models\User;
use App\Models\SCase;
use App\Models\MeetingRoom;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\MessagesResource;
use App\Http\Requests\MessageRequest;
use App\Models\System\System;
use PhpParser\Node\Stmt\TryCatch;
use App\Models\Log;
use Carbon\Carbon;

class MessageController extends Controller
{

    public function index(Request $request)
    {
        $user = auth()->user();
        $parent = isHasRole(System::USER_TYPE_PARENT_ROLE_NAME);
        $perPage = resolvePerPage($request, 10, 50);
        if ($perPage < 1) {
            $perPage = 10;
        }

        $case_id = $request->case_id;
        $with = ['user:id,name,image_path', 'user.roles:id,name,default_name'];
        if($request->case_id != null && $request->case_id != ''){
            $with[] = 'goal:id,title';
        }
        $messages = Message::with($with)
        ->when(
            $request->goal_id,
            fn ($q) => $q->where('goal_id',$request->goal_id)
        )
        ->when(
            $request->meeting_room_id,
            fn ($q) => $q->where('meeting_room_id',$request->meeting_room_id)
        )
        ->when(
            $parent,
            fn ($q) => $q->where('parents_can_see', 1)
        )
        ->when(
            $request->case_id,
            fn ($q) => $q
            ->whereHas('goal', function ($query) use ($case_id) {
                $query->where('goals.case_id', $case_id);
            })
        )
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

        return apiPaginateResponse($messages, MessagesResource::collection($messages));
    }

    public function put(MessageRequest $request, $message = null){

        $meetingRoom = null;
        $input = $request->validated();
        $input['user_id'] = auth()->user()->id;
        if($request->meeting_room_id && $request->meeting_room_id > 0) {
            $meetingRoom = MeetingRoom::find($request->meeting_room_id);
            if($meetingRoom->type == MeetingRoom::TYPE_GENERAL) {
                $input['parents_can_see'] = 1;
            }
        }
        $message = Message::updateOrCreate(['id' => $message], $input);

        $goal = null;
        if($request->goal_id)
            $goal = Goal::find($request->get('goal_id'));

        if($goal && $request->parents_can_see && $request->parents_can_see == 1) {
            
            $case = SCase::find($goal->case_id);
            $whatsAppMessage = "{$request->content}";
            $url = config('app.front_url')."/cases/view/messages/{$case->id}";

            \Log::channel('whatsapp')->info('whatsApp Message');
            \Log::channel('whatsapp')->info('Message: '.$whatsAppMessage);
            \Log::channel('whatsapp')->info('URL: '.$url);

            sendWhatsAppMessage(config('motabaa.dev.phone'), $whatsAppMessage, $url);
            sendWhatsAppMessage(config('motabaa.dev.phone_2'), $whatsAppMessage, $url);

            $parentsPhone = [];
            if($case->parents && !config('app.debug')) {
                foreach ($case->parents as $parent) {
                    if($parent->phone) {
                        $parentsPhone[] = $parent->phone;
                        sendWhatsAppMessage($parent->phone, $whatsAppMessage, $url);
                    }
                }
            }
            \Log::channel('whatsapp')->info($parentsPhone);
            $logData = [];
            $logData['message'] = $whatsAppMessage;
            $logData['url'] = $url;
            $logData['phones'] = implode(", ", $parentsPhone);
            Log::add('add_message', $message, $message->id, json_encode($logData));
        }

        if($request->meeting_room_id && $request->meeting_room_id > 0) {

            $whatsAppMessage = "{$request->content}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";

            $roles = [];
            if(!$meetingRoom)
                $meetingRoom = MeetingRoom::find($request->meeting_room_id);

            if($meetingRoom->type == MeetingRoom::TYPE_GENERAL) {
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
            Log::add('add_message', $message, $message->id, json_encode($logData));
        }

        try {
            if(($input['log_work'] == '1' || $input['log_work'] == 1) && $input['goal_id'] && $goal){
                if(($goal->last_started_session < $goal->ended_session) || $goal->last_started_session == null){
                    if(!$goal->started_session){
                        $goal->started_session = now();
                    }
                    $goal->last_started_session = now();
                }else if (($goal->last_started_session > $goal->ended_session) || $goal->ended_session == null){
                    $goal->ended_session = now();
                }
                $goal->save();
            }
        } catch (\Throwable $th) {}

        if($request->hasFile('image')) {
            $message->saveFile($input['image'], 'image');
        }
        else if($request->hasFile('file')) {
            $message->saveFile($input['file'], 'file');
        }
        else if($request->hasFile('video')) {
            $message->saveFile($input['video'], 'video');
        }

        return success();
    }

    public function delete($id) {

        $record = Message::find($id);
        $record->deleteFolder();
        $record->delete();
        Log::add('delete_message', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }

    public function file(Message $message) {

        $fileURL = $message->urlFile();
        if($fileURL)
            return apiResponse($fileURL);

        $imageURL = $message->urlImage();
        if($imageURL)
            return apiResponse(['file'=> $imageURL, 'is_video'=> false]);

        $videoURL = $message->urlVideo();
        if($videoURL)
            return apiResponse(['file'=> $videoURL, 'is_video'=> true]);

        return apiResponse(null);
    }
}
