<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Questionnaire;
use App\Models\QuestionnaireTask;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireAnswer;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\QuestionnaireResource;
use App\Http\Resources\QuestionnaireTaskResource;
use App\Http\Resources\QuestionnaireQuestionResource;
use App\Http\Resources\QuestionnaireAnswerResource;
use App\Models\Term;
use App\Http\Resources\TermResource;
use Carbon\Carbon;
use App\Models\Log;

class QuestionnaireController extends Controller
{

    public function index(Request $request){

        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);

        $tasks = QuestionnaireTask::select('questionnaires_tasks.*')
        ->where('questionnaires_tasks.center_id', $center)
        ->when(
            $request->q,
            fn ($q) => $q->where('title', 'like',"%{$keywords}%")
        )
        ->where(function ($query) use ($request) {
            if($request->from_date && $request->to_date) {
                $query->orWhereBetween("starts_at", [$request->from_date, $request->to_date]);
                $query->orWhereBetween("ends_at", [$request->from_date, $request->to_date]);
            }
            else if($request->from_date) {
                $query->whereDate("starts_at", "<=", $request->from_date);
            }
            else if($request->to_date) {
                $query->whereDate("ends_at", ">=", $request->to_date);
            }
        })
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );

        if (!Term::applyVisibleTerm($tasks, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }

        $tasks = $tasks->paginate($perPage);
        return apiPaginateResponse($tasks, QuestionnaireTaskResource::collection($tasks));
    }

    public function questionnaireTask(Request $request){
            
        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }
        
        $currentDate = Carbon::now()->toDateString();
        $currentTerm = Term::whereDate('starts_at', '<=', $currentDate)
        ->whereDate('ends_at', '>=', $currentDate)
        ->where('center_id', $center)
        ->first();
        if($currentTerm) {

            $task = QuestionnaireTask::where('term_id', $currentTerm->id)
            ->where('center_id', $center)
            ->first();

            if($task && $task->isOpen())
                return apiResponse($task);
            
            return apiResponse(false);
        }

        return apiResponse(false);
    }

    public function show(Request $request, $task){

        $task = QuestionnaireTask::find($task);

        if($task) {

            $user = auth()->user();
            $center = null;
            if($request->center_id && $user->isInCenter($request->center_id)) {
                $center = $request->center_id;
            }
            else if($user->centers){ 
                $center = $user->centers[0]->id;
            }

            $task = $task->load('questionnaire');
            if($task->center_id == $center)
                $task = new QuestionnaireTaskResource($task);
            else
                $task = null;
        }

        return apiResponse($task);
    }

    public function put(Request $request){

        if($request->starts_at > $request->ends_at)
            return response()->json(['errors' => ['error' => [__('validation.Start Date after End Date')]]], 422);
        
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        if($request->id>0) {

            $message = 'Updated successfully.';
            $task = QuestionnaireTask::withTrashed()->find($request->id);
        }
        else {

            $center = Term::resolveCenterId($user, $request->center_id);

            if(!$center)
                return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);

            $questionnaire = Questionnaire::first();

            if(!$questionnaire) {
                return response()->json(['errors' => ['error' => [__("validation.Questionnaire does not exist")]]], 422);
            }
            
            $task = QuestionnaireTask::where('questionnaire_id', $questionnaire->id)
            ->where('center_id', $center)
            ->where('term_id', $request->term_id)
            ->exists();
            if($task) {
                return response()->json(['errors' => ['error' => [__("validation.questionnaires.Questionnaire already exists")]]], 422);
            }

            $message = 'Added successfully.';
            $task = new QuestionnaireTask();
            $task->questionnaire_id = $questionnaire->id;
            $task->center_id = $center;
        }

        $task->term_id = $request->term_id;
        $task->starts_at = $request->starts_at;
        $task->ends_at = $request->ends_at;
        $task->save();

        $type = 'add_questionnaire';
        if($request->id>0)
            $type = 'edit_questionnaire';

        Log::add($type, $task, $task->id, '');

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function delete($id) {

        $record = QuestionnaireTask::find($id);
        $denied = Term::abortIfInaccessible(auth()->user(), $record?->center_id, $record?->term_id);
        if ($denied) {
            return $denied;
        }
        $record->delete();
        Log::add('delete_questionnaire', $record, $record->id, '');
        return response()->json(['message' => 'Deleted successfully.', 'status' => true]);
    }

    public function restore($id) {

        $record = QuestionnaireTask::with('questionnaire')->withTrashed()->find($id);
        $denied = Term::abortIfInaccessible(auth()->user(), $record?->center_id, $record?->term_id);
        if ($denied) {
            return $denied;
        }

        $task = QuestionnaireTask::where('center_id', $record->center_id)
        ->where('questionnaire_id', $record->questionnaire_id)
        ->where('term_id', $record->term_id)
        ->exists();
        if($task) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.Questionnaire already exists')]]], 422);
        }

        $record->restore();
        Log::add('restore_questionnaire', $record, $record->id, '');
        return response()->json(['message' => 'Restored successfully.', 'status' => true]);
    }

    public function fetchQuestions(Request $request, QuestionnaireTask $task){

        $user = auth()->user();
        if(!$task->isOpen()) {
            return response()->json(['errors' => ['error' => [__('validation.questionnaires.Questionnaire closed')]]], 422);
        }

        $questions = QuestionnaireQuestion::where('questionnaires_questions.questionnaire_id', $task->questionnaire_id);

        if(auth()->user()) {

            $user = auth()->user();
            $questions->leftJoin('questionnaires_answers', function($join) use($user, $task) {
                $join->on('questionnaires_answers.question_id', '=', 'questionnaires_questions.id');
                $join->where('questionnaires_answers.task_id', $task->id);
                $join->where('questionnaires_answers.user_id', $user->id);
            })
            ->select(
                'questionnaires_questions.*', 
                'questionnaires_answers.id as answer_id', 
                'questionnaires_answers.answer as answer'
            );
        }

        $questions = $questions->orderBy('questionnaires_questions.id', 'ASC')->get();

        return apiResponse(['taskID'=> $task->id, 'questions'=> $questions, 'questionnaire_title'=> $task->questionnaire->getNameAttribute()]);
    }

    public function fetchQuestionsWithAnswers(Request $request){

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers){ 
            $center = $user->centers[0]->id;
        }

        $questionsData = [];
        $task = QuestionnaireTask::find($request->task_id);
        if ($task) {

            $questions = QuestionnaireQuestion::where('questionnaire_id', $task->questionnaire_id)
            ->orderBy('questionnaires_questions.id', 'ASC')
            ->get();

            foreach ($questions as $question) {

                $answers = QuestionnaireAnswer::where('task_id', $request->task_id)
                ->where('question_id', $question->id)
                ->pluck('answer')
                ->toArray();

                $answered = count($answers);
                $questionsData[$question->getNameAttribute()]['type'] = $question->type;
                $questionsData[$question->getNameAttribute()]['answered'] = $answered;
                $questionsData[$question->getNameAttribute()]['skipped'] = ($task->count - $answered);

                $nYes = 0;
                $nNo = 0;
                foreach ($answers as $key => $answer) {

                    if($question->type == 2) {
                        if($answer == 'yes')
                            $nYes++;
                        if($answer == 'no')
                            $nNo++;
                    }
                    else if($question->type == 1) {
                        $questionsData[$question->getNameAttribute()]['answers'][] = ['id'=> $key+1, 'answer'=> $answer];
                    }
                }

                $nAnswered = $nYes + $nNo;
                $questionsData[$question->getNameAttribute()]['n_yes'] = ($nAnswered>0) ? ($nYes*100)/$nAnswered : 0;
                $questionsData[$question->getNameAttribute()]['n_no'] = ($nAnswered>0) ? ($nNo*100)/$nAnswered : 0;
            }
        }

        return apiResponse($questionsData);
    }

    public function addAnswers(Request $request, QuestionnaireTask $task){

        $isNew = false;
        $userID = 0;
        if(auth()->user())
            $userID = auth()->user()->id;

        foreach ($request->all() as $key => $value) {

            $answer = null;
            if(auth()->user()) {
                $answer = QuestionnaireAnswer::where('task_id', $task->id)
                ->where('user_id', $userID)
                ->where('question_id', $key)
                ->first();
            }

            if($value) {

                if(!$answer) {

                    $isNew = true;
                    $answer = new QuestionnaireAnswer();
                    $answer->task_id = $task->id;
                    $answer->user_id = $userID;
                    $answer->question_id = $key;
                }
                
                $answer->answer = $value;
                $answer->save();
            }
            else if($answer) {
                
                $answer->delete();
            }
        }

        if($isNew) {

            $task->count += 1;
            $task->save();
        }

        if($isNew)
            return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
        else
            return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }
}
