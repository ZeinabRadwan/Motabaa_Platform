<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\SCase;
use App\Models\User;
use App\Models\GoalEvaluation;
use App\Models\GoalEvaluationStep;
use App\Http\Resources\GoalsResource;
use App\Http\Requests\GoalRequest;
use App\Http\Resources\GoalEvaluationStepResource;
use App\Models\Assessment;
use App\Models\Message;
use App\Models\AssessmentEvaluation;
use App\Models\SCaseGoalStatus;
use App\Models\EvaluationMethodValue;
use App\Models\Center;
use Illuminate\Support\Facades\DB;
use App\Models\System\PDF;
use Carbon\Carbon;
use App\Models\Log;
use App\Models\Term;
use App\Models\System\System;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $dateNow = Carbon::now()->toDateString();

        $perPage = resolvePerPage($request);
        $feild_id = $request->feild_id;
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $keyword = mb_ereg_replace(" ", "%", getFTS($request->q));
        
        $center = Term::resolveCenterId($user, $request->center_id);

        // Listing goals must not widen what this user can see, even when a case_id is sent.
        $restrictCases = !$user->can('admin_cases')
            && !Term::canManageAllCenterTerms($user, $center);

        $sessionsCountMin = $request->sessions_count_min;
        $sessionsCountMax = $request->sessions_count_max;
        $hasSessionsCountMin = $sessionsCountMin !== null && $sessionsCountMin !== '';
        $hasSessionsCountMax = $sessionsCountMax !== null && $sessionsCountMax !== '';

        $goals = Goal::select('goals.*')
        ->whereHas('case', function ($query) use ($center, $restrictCases, $user) {
            $query->where('scases.center_id', $center);
            if ($restrictCases) {
                $types = $this->scaseRelationshipTypesForRoles($user->roles);
                $query->whereExists(function ($pivot) use ($user, $types) {
                    $pivot->selectRaw('1')
                        ->from('scase_user')
                        ->join('users', 'users.id', '=', 'scase_user.user_id')
                        ->whereColumn('scase_user.scase_id', 'scases.id')
                        ->where('scase_user.user_id', $user->id)
                        ->whereNull('users.deleted_at');
                    if ($types === []) {
                        $pivot->whereRaw('0 = 1');
                    } else {
                        $pivot->whereIn('scase_user.relationship_type', $types);
                    }
                });
            }
        })
        ->when(
            $request->q,
            fn ($q) => $q->where(function ($query) use ($keyword) {
                $query->where('title', 'like',"%{$keyword}%")
                ->orWhere('title_local', 'like',"%{$keyword}%");
            })
        )
        ->when(
            $request->category,
            fn ($q) => $q->where('category',$request->category)
        )
        ->when(
            !$request->category,
            fn ($q) => $q->where('category', '!=', 'educational')
        )
        ->when(
            $request->case_id,
            fn ($q) => $q->where('case_id',$request->case_id)
        )
            ->when(
                $request->teacher_id,
                fn ($q) => $q->whereExists(function ($pivot) use ($request) {
                    $pivot->selectRaw('1')
                        ->from('scase_user')
                        ->join('users', 'users.id', '=', 'scase_user.user_id')
                        ->whereColumn('scase_user.scase_id', 'goals.case_id')
                        ->where('scase_user.user_id', $request->teacher_id)
                        ->where('scase_user.relationship_type', System::USER_TYPE_TEACHER)
                        ->whereNull('users.deleted_at');
                })
            )
            ->when(
                $request->specialist_id,
                fn ($q) => $q->whereExists(function ($pivot) use ($request) {
                    $pivot->selectRaw('1')
                        ->from('scase_user')
                        ->join('users', 'users.id', '=', 'scase_user.user_id')
                        ->whereColumn('scase_user.scase_id', 'goals.case_id')
                        ->where('scase_user.user_id', $request->specialist_id)
                        ->whereIn('scase_user.relationship_type', [
                            System::USER_TYPE_PHYSIOTHERAPIST,
                            System::USER_TYPE_OCCUPATIONAL_THERAPY,
                            System::USER_TYPE_SOCIAL,
                            System::USER_TYPE_PRONUNCIATION_SPEECH,
                            System::USER_TYPE_MENTAL,
                            System::USER_TYPE_PSYCHOTHERAPIST,
                        ])
                        ->whereNull('users.deleted_at');
                })
            )
            ->when(
                $feild_id && $feild_id != 'without_feild',
            fn ($q) => $q->whereExists(function ($assessment) use ($feild_id) {
                $assessment->selectRaw('1')
                    ->from('assessments')
                    ->whereColumn('assessments.id', 'goals.assessment_id')
                    ->where('assessments.parents_ids', 'like', "%#{$feild_id}_%")
                    ->whereNull('assessments.deleted_at');
            })
        )
        ->when(
            $feild_id == 'without_feild',
            fn ($q) => $q->whereNull('assessment_id')
        )
        ->when(
            $request->date_from && $request->date_to,
            fn ($q) => $q->where(function ($query) use ($date_from, $date_to) {
                $query
                // ->where('date_from', '<=', $date_from)
                ->where(function ($query) use ($date_from, $date_to) {
                    $query->where('date_from', '>=', $date_from)
                    ->orWhereBetween('date_to', [$date_from, $date_to]);
                })
                ->where(function ($query) use ($date_from, $date_to) {
                    $query->where('date_to', '<=', $date_to)
                    ->orWhereBetween('date_from', [$date_from, $date_to]);
                    // ->orWhere('date_to', '>=', $date_from);
                })
                ->orWhere(function ($query) use ($date_from, $date_to) {
                    $query->where('date_from', '<=', $date_from)
                    ->where('date_to', '>=', $date_to);
                })
                ;
            })
        )
        ->when(
            $hasSessionsCountMin || $hasSessionsCountMax,
            function ($q) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                $q->where(function ($query) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                    $query->where(function ($independent) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                        $independent->where('category', 'independent');
                        if ($hasSessionsCountMin) {
                            $independent->has('evaluation_steps', '>=', (int) $sessionsCountMin);
                        }
                        if ($hasSessionsCountMax) {
                            $independent->has('evaluation_steps', '<=', (int) $sessionsCountMax);
                        }
                    })->orWhere(function ($other) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                        $other->where('category', '!=', 'independent');
                        $startedSessions = function ($messages) {
                            $messages->where('type', Message::SYS_STARTED_SESSION);
                        };
                        if ($hasSessionsCountMin) {
                            $other->whereHas('messages', $startedSessions, '>=', (int) $sessionsCountMin);
                        }
                        if ($hasSessionsCountMax) {
                            $other->whereHas('messages', $startedSessions, '<=', (int) $sessionsCountMax);
                        }
                    });
                });
            }
        )
        ->when(
            $request->period !== null && $request->period !== '' && Term::hasPeriodsTable(),
            function ($q) use ($request) {
                $periodIndex = (int) $request->period;

                $q->whereHas('term.periods', function ($query) use ($periodIndex) {
                    $query->where('period_index', $periodIndex)
                        ->where('term_periods.evaluation_at', '>=', DB::raw('goals.date_to'));
                });

                if ($periodIndex === 0) {
                    $q->whereHas('term', function ($query) {
                        $query->where('terms.starts_at', '<=', DB::raw('goals.date_from'));
                    });
                } else {
                    $q->where(function ($query) use ($periodIndex) {
                        $query->whereHas('term.periods', function ($subQuery) use ($periodIndex) {
                            $subQuery->where('period_index', $periodIndex - 1)
                                ->where('term_periods.evaluation_at', '<', DB::raw('goals.date_from'));
                        });
                        $query->orWhereNull('goals.value_id');
                        $query->orWhereHas('evaluationMethodValue', function ($hasQuery) {
                            $hasQuery->where('evaluation_methods_values.ability', '!=', 'power');
                        });
                    });
                }
            }
        )
        ->when(
            $request->period || $request->period == '0',
            fn ($q) => $q->leftJoin('goal_evaluations', function ($query) use($request) {
                $query->on('goal_evaluations.goal_id', 'goals.id')
                ->where('goal_evaluations.period', $request->period);
            })
            ->addSelect('goal_evaluations.value as evaluation_value')
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        );

        if (!Term::applyVisibleTerm($goals, $user, $center, $request->term_id)) {
            return Term::forbiddenResponse();
        }

        if($request->pdf) {
            $goals->with('assesment', 'assesment.parent', 'case:id,name')
                ->withCount([
                    'messages as started_sessions_count' => function ($query) {
                        $query->where('type', Message::SYS_STARTED_SESSION);
                    },
                    'evaluation_steps as evaluation_steps_count',
                ]);

            $evaluationMethod = 1;
            $data = \App\Http\Resources\PDF\PdfGoalsResource::collection($goals->get());
            $items = $data->toArray($request);

            $scase = [];
            if($request->case_id)
                $scase = SCase::find($request->case_id);

            $teacherOrSpecialist = null;
            if($request->category) {
                if($request->category == 'educational')
                    $teacherOrSpecialist = $scase->teacher()->first();
                else if($request->category == 'occupational therapy')
                    $teacherOrSpecialist = $scase->occupational_therapy()->first();
                else if($request->category == 'physical therapy')
                    $teacherOrSpecialist = $scase->physiotherapist()->first();
                else if($request->category == 'pronouncement')
                    $teacherOrSpecialist = $scase->pronunciation_speech()->first();
                else if($request->category == 'psychiatric treatment')
                    $teacherOrSpecialist = $scase->psychotherapist()->first();
                if($request->category == 'independent')
                    $teacherOrSpecialist = $scase->teacher()->first();
            }

            $periodTerm = $request->term_id ? Term::withPeriods()->find($request->term_id) : null;
            $periodName = ($periodTerm ? $periodTerm->periodTitle($request->period) : __('tr.goals.period_n', ['n' => ((int) $request->period) + 1])).' ';
            $periodTitle = $periodTerm ? $periodTerm->periodTitle($request->period) : __('tr.goals.period_n', ['n' => ((int) $request->period) + 1]);

            $title = null;
            $bladePath = null;
            $pdfData = null;
            if($request->pdf == 'case_planning'){

                $title = 'Case Planning';
                $bladePath = 'goals.case_planning';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                ];
            }
            else if($request->pdf == 'period_assessment'){

                $title = $periodName.'Period Assessment';
                $bladePath = 'goals.period_assessment';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                    'evaluation_method' => \App\Models\EvaluationMethod::find($evaluationMethod),
                    'period_level' => $request->period,
                    'period_title' => $periodTitle,
                ];
            }
            else if($request->pdf == 'services_case_planning'){
                
                $title = 'Case Planning';
                $bladePath = 'services.case_planning';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                ];
            }
            else if($request->pdf == 'services_period_assessment'){

                $title = $periodName.'Period Assessment';
                $bladePath = 'services.period_assessment';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                    'evaluation_method' => \App\Models\EvaluationMethod::find($evaluationMethod),
                    'period_level' => $request->period,
                    'period_title' => $periodTitle,
                ];
            }
            else if($request->pdf == 'independent_case_planning'){

                $title = 'Case Planning';
                $bladePath = 'independent.case_planning';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                ];
            }
            else if($request->pdf == 'independent_period_assessment'){

                $title = $periodName.'Period Assessment';
                $bladePath = 'independent.period_assessment';
                $pdfData = [
                    'center' => Center::find($center),
                    'items' => $items,
                    'case' => $scase,
                    'teacher_or_specialist' => $teacherOrSpecialist,
                    'evaluation_method' => \App\Models\EvaluationMethod::find($evaluationMethod),
                    'period_level' => $request->period,
                    'period_title' => $periodTitle,
                ];
            }

            if($title && $bladePath && $pdfData) {

                $token = PDF::savePdf($bladePath, $pdfData,"{$title} {$dateNow}.pdf");
                return success(['url'=> route('file.download', ['token'=> $token])]);
            }
        }

        $listColumns = [
            'goals.id',
            'goals.case_id',
            'goals.term_id',
            'goals.assessment_id',
            'goals.title',
            'goals.title_local',
            'goals.custom_general_goal',
            'goals.custom_first_feild',
            'goals.started_session',
            'goals.last_started_session',
            'goals.ended_session',
            'goals.value',
            'goals.date_from',
            'goals.date_to',
            'goals.generalization',
            'goals.standard',
            'goals.deleted_at',
            'goals.category',
        ];
        if ($request->period !== null && $request->period !== '') {
            $listColumns[] = 'goal_evaluations.value as evaluation_value';
        }

        $goals = $goals
            ->select($listColumns)
            ->with([
                'case:id,name',
                'assesment:id,parent_id,parents_ids,title,title_local,evaluation_method_id',
                'assesment.parent:id,title,title_local',
            ])
            ->withCount([
                'messages as started_sessions_count' => function ($query) {
                    $query->where('type', Message::SYS_STARTED_SESSION);
                },
                'evaluation_steps as evaluation_steps_count',
            ])
            ->paginate($perPage);

        Assessment::primeLookups($goals->getCollection()->pluck('assesment'));

        return apiPaginateResponse($goals, GoalsResource::listCollection($goals));
    }

    private function scaseRelationshipTypesForRoles($roles): array
    {
        $types = [];
        foreach ($roles as $role) {
            switch ($role->default_name) {
                case 'parent':
                    $types[] = System::USER_TYPE_PARENT;
                    break;
                case 'teacher':
                    $types[] = System::USER_TYPE_TEACHER;
                    break;
                case 'physiotherapist_specialist':
                    $types[] = System::USER_TYPE_PHYSIOTHERAPIST;
                    break;
                case 'occupational_specialist':
                    $types[] = System::USER_TYPE_OCCUPATIONAL_THERAPY;
                    break;
                case 'psychotherapist_specialist':
                    $types[] = System::USER_TYPE_PSYCHOTHERAPIST;
                    break;
                case 'social_specialist':
                    $types[] = System::USER_TYPE_SOCIAL;
                    break;
                case 'pronunciation_speech_specialist':
                    $types[] = System::USER_TYPE_PRONUNCIATION_SPEECH;
                    break;
                case 'mental_disability_specialist':
                    $types[] = System::USER_TYPE_MENTAL;
                    break;
            }
        }

        return $types;
    }

    public function show(Request $request, Goal $goal){

        $user = auth()->user();
        $goal = $goal->load('case')->loadCount([
            'messages as started_sessions_count' => function ($query) {
                $query->where('type', Message::SYS_STARTED_SESSION);
            },
            'evaluation_steps as evaluation_steps_count',
        ]);

        if (!$goal->case) {
            return Term::forbiddenResponse();
        }

        $center = Term::resolveCenterId($user, $request->center_id) ?: (int) $goal->case->center_id;
        $denied = Term::abortIfInaccessible($user, $center, $goal->term_id);
        if ($denied) {
            return $denied;
        }
        if ((int) $goal->case->center_id !== (int) $center && !Term::canViewPastTerms($user)) {
            return Term::forbiddenResponse();
        }
        if (!SCase::userCanAccessCase($user, $goal->case)) {
            return Term::forbiddenResponse();
        }

        return apiResponse(new GoalsResource($goal));
    }

    public function put(GoalRequest $request, $goal = null){
        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }
        if ($goal > 0) {
            $existing = Goal::find($goal);
            $denied = Term::abortIfInaccessible($user, $center ?: optional($existing)->case?->center_id, optional($existing)->term_id);
            if ($denied) {
                return $denied;
            }
        }
        try {
            $type = 'add_goal';
            if($goal>0)
                $type = 'edit_goal';

            $goal = Goal::updateOrCreate(['id' => $goal], $request->validated());
            Log::add($type, $goal, $goal->id, '');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') != false) {
                return apiResponse(['errors' => ['error' => ['the_goal_already_exists']]], null, false, 406 );
            }
        }
        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function transferGoal(Request $request, Goal $goal){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id) ?: optional($goal->case)->center_id;
        $denied = Term::abortIfInaccessible($user, $center, $goal->term_id)
            ?: Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        if($goal->term_id == $request->term_id)
            return response()->json(['errors' => ['error' => [__('validation.goals.You need to choose another term')]]], 422);

        if($goal->assessment_id) {
            $termGoalExists = Goal::where('term_id', $request->term_id)
            ->where('case_id', $goal->case_id)
            ->where('assessment_id', $goal->assessment_id)
            ->exists();
            if($termGoalExists)
                return response()->json(['errors' => ['error' => [__('validation.goals.This goal exists in this term')]]], 422);
        }

        $goal->term_id = $request->term_id;
        $goal->save();
        Log::add('transfer_goal', $goal, $goal->id, '');

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function periodStatus(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        $sCaseGoalStatus = null;
        if($request->case_id && $request->term_id && $request->category && $request->period>=0) {
            $sCaseGoalStatus = SCaseGoalStatus::where('case_id', $request->case_id)
            ->where('term_id', $request->term_id)
            ->where('category', $request->category)
            ->where('period', $request->period)
            ->first();
        }
        return apiResponse($sCaseGoalStatus ? $sCaseGoalStatus->status : null);
    }

    public function periodAction(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        $status = SCaseGoalStatus::STATUS_UNFINISHED;
        if($request->status == 'finish')
            $status = SCaseGoalStatus::STATUS_FINISHED;

        if($request->case_id && $request->term_id && $request->category && $request->period>=0) {
            $scaseGoalStatus = SCaseGoalStatus::updateOrCreate(
                [
                    'case_id' => $request->case_id,
                    'term_id' => $request->term_id,
                    'category' => $request->category,
                    'period' => $request->period
                ],
                [
                    'status' => $status
                ]
            );
            
            if($status == SCaseGoalStatus::STATUS_FINISHED) {

                $case = SCase::find($request->case_id);
                $whatsAppMessage = "تم إصدار التقييم الشهري ل ({$case->name}) في مجال (".__('tr.cases.'.$request->category).").";
                $url = "";
                if($request->category == 'educational') {
                    $url = config('app.front_url')."/goals/list/{$case->id}/{$request->term_id}";
                }
                else if($request->category == 'independent') {
                    $url = config('app.front_url')."/independent_goals/list/{$case->id}/{$request->term_id}";
                }
                else {
                    $request->category = str_replace(' ', '%20', $request->category);
                    $url = config('app.front_url')."/services/list/{$case->id}/{$request->term_id}/{$request->category}";
                }

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
                Log::add('finish_goal_evaluations', $scaseGoalStatus, $scaseGoalStatus->id, json_encode($logData));
            }
            Log::add('unfinish_goal_evaluations', $scaseGoalStatus, $scaseGoalStatus->id, json_encode($logData));
        }

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function start_session(Request $request, Goal $goal){
        $denied = Term::abortIfInaccessible(auth()->user(), optional($goal->case)->center_id, $goal->term_id);
        if ($denied) {
            return $denied;
        }
        if(!$goal->started_session){
            $goal->started_session = $request->get('datetime');
        }
        $goal->last_started_session = $request->get('datetime');
        $goal->save();
        $input = [
            'user_id' => auth()->user()->id,
            'goal_id' => $goal->id,
            'content' => "started_session_".$request->get('type'),
            'type' => Message::SYS_STARTED_SESSION,
            'created_at' => $request->get('datetime')
        ];
        $message = Message::create($input);
        Log::add('start_session_message', $message, $message->id, '');
        return success();
    }

    public function end_session(Request $request, Goal $goal){
        $denied = Term::abortIfInaccessible(auth()->user(), optional($goal->case)->center_id, $goal->term_id);
        if ($denied) {
            return $denied;
        }
        $goal->ended_session = $request->get('datetime');
        $goal->save();
        $input = [
            'user_id' => auth()->user()->id,
            'goal_id' => $goal->id,
            'content' => "ended_session_".$request->get('type'),
            'type' => Message::SYS_ENDED_SESSION,
            'created_at' => $request->get('datetime')
        ];
        $message = Message::create($input);
        Log::add('end_session_message', $message, $message->id, '');
        return success();
    }

    public function delete($id)
    {
        $record = Goal::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $denied = Term::abortIfInaccessible(auth()->user(), optional($record->case)->center_id, $record->term_id);
        if ($denied) {
            return $denied;
        }
        AssessmentEvaluation::where('assesment_id', $record->assessment_id)->where('case_id', $record->case_id)->update(
            [
                "value" => null,
                "ability" => null,
            ]
        );
        $record->delete();
        Log::add('delete_goal', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restore($id)
    {
        $record = Goal::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $denied = Term::abortIfInaccessible(auth()->user(), optional($record->case)->center_id, $record->term_id);
        if ($denied) {
            return $denied;
        }
        AssessmentEvaluation::where('assesment_id', $record->assessment_id)->where('case_id', $record->case_id)->update(
            [
                "value" => $record->value,
                "ability" => 'weak',
            ]
        );
        $record->restore();
        Log::add('restore_goal', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function selectItems(Request $request)
    {
        $user = auth()->user();
        $limit = resolveSelectLimit($request, 50, 200);
        $keyword = mb_ereg_replace(" ", "%", getFTS($request->q));
        $date_from = $request->date_from;
        $date_to = $request->date_to;
        $sessionsCountMin = $request->sessions_count_min;
        $sessionsCountMax = $request->sessions_count_max;
        $hasSessionsCountMin = $sessionsCountMin !== null && $sessionsCountMin !== '';
        $hasSessionsCountMax = $sessionsCountMax !== null && $sessionsCountMax !== '';

        $center = Term::resolveCenterId($user, $request->center_id);
        $visibleTerm = Term::visibleTermId($user, $center, $request->term_id);
        if (!$visibleTerm['ok']) {
            return Term::forbiddenResponse();
        }

        // Listing goals must not widen what this user can see, even when a case_id is sent.
        $restrictCases = !$user->can('admin_cases')
            && !Term::canManageAllCenterTerms($user, $center);

        $goals = Goal::select(
            'goals.id',
            'goals.case_id',
            'goals.title',
            'goals.title_local',
            'goals.category',
            'goals.started_session',
            'goals.last_started_session',
            'goals.ended_session'
        )
            ->with('case:id,name')
            ->withCount([
                'messages as started_sessions_count' => function ($query) {
                    $query->where('type', Message::SYS_STARTED_SESSION);
                },
                'evaluation_steps as evaluation_steps_count',
            ])
            ->whereHas('case', function ($query) use ($center, $restrictCases, $user) {
                $query->where('scases.center_id', $center);
                if ($restrictCases) {
                    $query->usersRoles($user->roles, $user->id);
                }
            })
            ->when(
                $request->q,
                fn ($q) => $q->where(function ($query) use ($keyword) {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('title_local', 'like', "%{$keyword}%");
                })
            )
            ->when(
                $request->category,
                fn ($q) => $q->where('category', $request->category)
            )
            ->when(
                !$request->category,
                fn ($q) => $q->where('category', '!=', 'educational')
            )
            ->when(
                $request->case_id,
                fn ($q) => $q->where('case_id', $request->case_id)
            )
            ->when(
                $request->teacher_id,
                fn ($q) => $q->whereExists(function ($pivot) use ($request) {
                    $pivot->selectRaw('1')
                        ->from('scase_user')
                        ->join('users', 'users.id', '=', 'scase_user.user_id')
                        ->whereColumn('scase_user.scase_id', 'goals.case_id')
                        ->where('scase_user.user_id', $request->teacher_id)
                        ->where('scase_user.relationship_type', System::USER_TYPE_TEACHER)
                        ->whereNull('users.deleted_at');
                })
            )
            ->when(
                $request->specialist_id,
                fn ($q) => $q->whereExists(function ($pivot) use ($request) {
                    $pivot->selectRaw('1')
                        ->from('scase_user')
                        ->join('users', 'users.id', '=', 'scase_user.user_id')
                        ->whereColumn('scase_user.scase_id', 'goals.case_id')
                        ->where('scase_user.user_id', $request->specialist_id)
                        ->whereIn('scase_user.relationship_type', [
                            System::USER_TYPE_PHYSIOTHERAPIST,
                            System::USER_TYPE_OCCUPATIONAL_THERAPY,
                            System::USER_TYPE_SOCIAL,
                            System::USER_TYPE_PRONUNCIATION_SPEECH,
                            System::USER_TYPE_MENTAL,
                            System::USER_TYPE_PSYCHOTHERAPIST,
                        ])
                        ->whereNull('users.deleted_at');
                })
            )
            ->when(
                $visibleTerm['term_id'] === 0,
                fn ($q) => $q->whereRaw('0 = 1')
            )
            ->when(
                $visibleTerm['term_id'],
                fn ($q) => $q->where('term_id', $visibleTerm['term_id'])
            )
            ->when(
                $request->date_from && $request->date_to,
                fn ($q) => $q->where(function ($query) use ($date_from, $date_to) {
                    $query->where(function ($query) use ($date_from, $date_to) {
                        $query->where('date_from', '>=', $date_from)
                            ->orWhereBetween('date_to', [$date_from, $date_to]);
                    })
                    ->where(function ($query) use ($date_from, $date_to) {
                        $query->where('date_to', '<=', $date_to)
                            ->orWhereBetween('date_from', [$date_from, $date_to]);
                    })
                    ->orWhere(function ($query) use ($date_from, $date_to) {
                        $query->where('date_from', '<=', $date_from)
                            ->where('date_to', '>=', $date_to);
                    });
                })
            )
            ->when(
                $hasSessionsCountMin || $hasSessionsCountMax,
                function ($q) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                    $q->where(function ($query) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                        $query->where(function ($independent) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                            $independent->where('category', 'independent');
                            if ($hasSessionsCountMin) {
                                $independent->has('evaluation_steps', '>=', (int) $sessionsCountMin);
                            }
                            if ($hasSessionsCountMax) {
                                $independent->has('evaluation_steps', '<=', (int) $sessionsCountMax);
                            }
                        })->orWhere(function ($other) use ($hasSessionsCountMin, $hasSessionsCountMax, $sessionsCountMin, $sessionsCountMax) {
                            $other->where('category', '!=', 'independent');
                            $startedSessions = function ($messages) {
                                $messages->where('type', Message::SYS_STARTED_SESSION);
                            };
                            if ($hasSessionsCountMin) {
                                $other->whereHas('messages', $startedSessions, '>=', (int) $sessionsCountMin);
                            }
                            if ($hasSessionsCountMax) {
                                $other->whereHas('messages', $startedSessions, '<=', (int) $sessionsCountMax);
                            }
                        });
                    });
                }
            )
            ->orderBy('goals.id')
            ->limit($limit)
            ->get()
            ->map(function ($goal) {
                $isEndedSession = false;
                if ($goal->ended_session && $goal->last_started_session) {
                    $isEndedSession = Carbon::parse($goal->ended_session)->isAfter($goal->last_started_session);
                }

                return [
                    'id' => $goal->id,
                    'title' => $goal->goal_title,
                    'case' => $goal->case ? [
                        'id' => $goal->case->id,
                        'name' => $goal->case->name,
                    ] : null,
                    'started_session' => $goal->started_session,
                    'last_started_session' => $goal->last_started_session,
                    'ended_session' => $goal->ended_session,
                    'is_ended_session' => $isEndedSession,
                    'sessions_count' => (int) ($goal->category === 'independent'
                        ? ($goal->evaluation_steps_count ?? 0)
                        : ($goal->started_sessions_count ?? 0)),
                ];
            });

        return apiResponse($goals);
    }

    public function filter(Request $request){

        $user = auth()->user();
        $center = Term::resolveCenterId($user, $request->center_id);
        $denied = Term::abortIfInaccessible($user, $center, $request->term_id);
        if ($denied) {
            return $denied;
        }

        // Case and term are optional: without them every domain the user may see
        // is listed, so the dropdown is usable on its own.
        $restrictCases = !$user->can('admin_cases')
            && !Term::canManageAllCenterTerms($user, $center);

        $goals = Goal::query()
        ->join('assessments', 'assessments.id', '=', 'goals.assessment_id')
        ->whereNull('assessments.deleted_at')
        ->whereHas('case', function ($query) use ($center, $restrictCases, $user) {
            $query->where('scases.center_id', $center);
            if ($restrictCases) {
                $query->usersRoles($user->roles, $user->id);
            }
        })
        ->when(
            $request->category,
            fn ($q) => $q->where('goals.category', $request->category)
        )
        ->when(
            $request->case_id,
            fn ($q) => $q->where('goals.case_id', $request->case_id)
        );

        if (!Term::applyVisibleTerm($goals, $user, $center, $request->term_id, 'goals.term_id')) {
            return Term::forbiddenResponse();
        }

        $parentsIds = $goals->distinct()->pluck('assessments.parents_ids');

        $feilds = [];
        foreach($parentsIds as $parentsId){
            $feild = Assessment::getAssessmentViaSteps($parentsId, 1);
            if($feild)
                $feilds[] = $feild;
        }
        $feilds = array_unique($feilds);
        $assessments = Assessment::with(['parent'])->whereIn('id', $feilds)->get();

        $selectItem = [];
        foreach($assessments as $assessment){
            $selectItem[] = [
                'title' => $assessment->parent ?  $assessment?->assessment_title . " ({$assessment->parent?->assessment_title})" : $assessment?->assessment_title,
                'value' => $assessment->id,
            ];
        }

        usort($selectItem, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return success($selectItem);
    }

    public function update_evaluation(Request $request){

        $user = auth()->user();
        foreach ($request->input('evaluation_goals') as $evaluationGoals) {
            $goal = Goal::find($evaluationGoals['goal_id']);
            $denied = Term::abortIfInaccessible($user, optional($goal?->case)->center_id, optional($goal)->term_id);
            if ($denied) {
                return $denied;
            }
        }

        foreach ($request->input('evaluation_goals') as $evaluationGoals) {
            $goalEvaluation = GoalEvaluation::where('goal_id', $evaluationGoals['goal_id'])->where('period', $evaluationGoals['period'])->first();
            if(!$goalEvaluation) {
                $goalEvaluation = new GoalEvaluation();
                $goalEvaluation->goal_id = $evaluationGoals['goal_id'];
                $goalEvaluation->period = $evaluationGoals['period'];
            }

            $goalEvaluation->value = $evaluationGoals['value'];
            $goalEvaluation->save();

            $goal = Goal::with('term.periods')->where('id', $goalEvaluation->goal_id)->first();
            if($goal) {
                $lastPeriodIndex = (string) ($goal->term ? $goal->term->lastPeriodIndex() : 3);
                if((string) $goalEvaluation->period !== $lastPeriodIndex) {
                    $finalEvaluation = GoalEvaluation::where('goal_id', $goalEvaluation->goal_id)
                        ->where('period', $lastPeriodIndex)
                        ->first();

                    if($finalEvaluation)
                        $goalEvaluation = $finalEvaluation;
                }

                $goal->value = $goalEvaluation->value;
                $goal->save();

                $ability = null;
                if($goalEvaluation->value_id) {
                    
                    $EvaluationMethodValue = EvaluationMethodValue::find($goalEvaluation->value_id);
                    $ability = $EvaluationMethodValue ? $EvaluationMethodValue->ability : null;
                }
                else {

                    $evaluationMethodID = $goal->assesment ? $goal->assesment->evaluation_method_id : 1;
                    $EvaluationMethodValue = EvaluationMethodValue::where('evaluation_method_id', $evaluationMethodID)
                    ->where('value', $goal->value)
                    ->first();
                    $ability = $EvaluationMethodValue ? $EvaluationMethodValue->ability : null;
                }

                if($goal->assessment_id){
                    $assessmentEvaluation = AssessmentEvaluation::updateOrCreate(
                        [
                            'assesment_id' => $goal->assessment_id,
                            'case_id' => $goal->case_id
                        ],
                        [
                            'value' => $goal->value,
                            'ability' => $ability
                        ]
                    );
                }
            }

            Log::add('put_goal_evaluation', $goalEvaluation, $goalEvaluation->id, '');
        }

        return success();
    }

    public function evaluations(Request $request){

        $perPage = resolvePerPage($request);
        if ($perPage < 1) {
            $perPage = 10;
        }
        
        $textSearch = mb_ereg_replace(" ", "%", getFTS($request->q));

        $evaluationsSteps = GoalEvaluationStep::when(
            $request->goal_id,
            fn ($q) => $q->Where("goal_id", $request->goal_id)
        )
        ->orderBy('date', 'DESC')
        ->orderBy('value', 'ASC')
        ->paginate($perPage);

        return apiPaginateResponse($evaluationsSteps, GoalEvaluationStepResource::collection($evaluationsSteps));
    }

    public function putEvaluationStep(Request $request){
            
        $goalEvaluationStep = new GoalEvaluationStep();
        if($request->id>0)
            $goalEvaluationStep = GoalEvaluationStep::find($request->id);

        $goalEvaluationStep->goal_id = $request->goal_id;
        $goalEvaluationStep->service_type = $request->service_type;
        $goalEvaluationStep->date = $request->date;
        $goalEvaluationStep->time = $request->time;
        $goalEvaluationStep->value = $request->value;
        $goalEvaluationStep->save();
        Log::add('put_goal_evaluation_step', $goalEvaluationStep, $goalEvaluationStep->id, '');

        return success();
    }

    public function deleteEvaluationStep($id)
    {
        $record = GoalEvaluationStep::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $oldRecord = clone $record;
        $record->delete();
        Log::add('delete_goal_evaluation_step', $oldRecord, $oldRecord->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }
}
