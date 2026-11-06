<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\GoalEvaluation;
use App\Mail\SystemMail;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('/ems/users/upload_file/{user}', [App\Http\Controllers\Admin\UserController::class, 'uploadFile']);
Route::post('/auth/login', [App\Http\Controllers\Auth\Admin\AuthController::class, 'login']);
Route::post('/auth/password/reset', 'App\Http\Controllers\Auth\Api\PasswordResetController@create');
Route::post('/auth/password/reset/{token}', 'App\Http\Controllers\Auth\Api\PasswordResetController@reset');
Route::post('/users/register', [App\Http\Controllers\Admin\UserController::class, 'register']);
Route::get('/users/verify/{token}', [App\Http\Controllers\Admin\UserController::class, 'verify'])->name('verify_user');

Route::get('/file/download', [App\Http\Controllers\General\FileController::class, 'download'])->name('file.download');

Route::prefix('questionnaires')->middleware(['auth.sanctum.optional'])->group(function () {
    Route::post('/{task}/fetch/questions', [App\Http\Controllers\General\QuestionnaireController::class, 'fetchQuestions']);
    Route::post('/{task}/add_answers', [App\Http\Controllers\General\QuestionnaireController::class, 'addAnswers']);
});

Route::prefix('meeting-rooms/invitations')->middleware(['auth.sanctum.optional'])->group(function () {
    Route::get('/status', [App\Http\Controllers\General\MeetingRoomController::class, 'invitationStatus']);
});

Route::prefix('centers')->group(function () {
    Route::get('/packages', [App\Http\Controllers\General\CenterController::class, 'packages']);
});

Route::get('/mail', function () {
    abort_unless(app()->environment('local'), 404);

    $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
        'id' => 2, 
        'email' => "ezzeldeen.atef.z@gmail.com"
    ]));
    $title = "رابط التفعيل";
    $systemMail = new SystemMail(['title'=> $title, 'verify_link' => route("verify_user", ['token'=> $token]), 'name'=> "Ezzeldeen"]);
    //dd($systemMail);
    $systemMail->submit("ezzeldeen.atef.z@gmail.com");

    dd("done");
});

Route::get('/upload_files_to_bunny', function () {
    abort_unless(app()->environment('local'), 404);

    ini_set('max_execution_time', 3600);
    $specificDate = '2023-8-25';
    $files = Storage::allFiles('public/');
    $filesAfterDate = collect($files)->filter(function ($file) use ($specificDate) {
        return filemtime(storage_path('app').'/'.$file) > strtotime($specificDate);
    })->values();
    foreach($filesAfterDate as $file) {
        if(str_contains($file, 'gitignore'))
            continue;
        $fileFolders = explode('/', $file);
        $nFolders = count($fileFolders);
        $fileName = $fileFolders[$nFolders-1];
        $folderName = str_replace("/{$fileName}", '', $file);
        $folderName = str_replace('public/', '', $folderName);
        $folderName = str_replace('child_case', 'cases', $folderName);
        $fileName = str_replace(' ', '_', $fileName);
        $bunnyFiles = fetchFilesFromBunny($folderName);
        if(Str::contains($bunnyFiles, '"ObjectName":"'.$fileName.'"'))
            continue;
        uplaodFileToBunny($folderName, $fileName, storage_path('app/'.$file));
        d($file);
    }
    d('Done');
});

// Route::get('/reorder_goals_steps', function () {

//     $order = 1;
//     $goalID = 0;
//     $goalsSteps = \App\Models\GoalsSteps::orderBy('goal_id', 'ASC')->orderBy('order', 'ASC')->get();
//     foreach ($goalsSteps as $goalStep) {
//         if($goalID != $goalStep->goal_id) {
//             $order = 1;
//             $goalID = $goalStep->goal_id;
//         }
//         $goalStep->order = $order;
//         $goalStep->save();
//         d("order: {$order} - goalID: {$goalID} - id: {$goalStep->id} - procedural_objectives: {$goalStep->procedural_objectives}");
//         $order++;
//     }
//     dd('Done');
// });

Route::get('/fix', function () {
    abort_unless(app()->environment('local'), 404);

    $records = GoalEvaluation::selectRaw("goal_id, period, count(*) as count")->groupBy("goal_id", "period")->having("count", ">", 1)->get()->toArray();
    
    foreach ($records as $record) {
        d($record);
        $goalsEvaluations = GoalEvaluation::where('goal_id', $record['goal_id'])
        ->where('period', $record['period'])
        ->orderBy('value', 'DESC')
        ->get();

        $i = 0;
        foreach ($goalsEvaluations as $goalEvaluation) {
            $i++;
            if($i==1) {
                d($goalEvaluation->getAttributes(), "green");
                continue;
            }

            d($goalEvaluation->getAttributes(), "red");
            $goalEvaluation->delete();
        }
    }

    dd("done");
});


// Route::get('/fix', function () {

// 	d("fix Assessment(s)", "green");
// 	$assessments = \App\Models\Assessment::withTrashed()->get();
// 	foreach ($assessments as $assessment) {
// 		$assessment->save();
// 	}

// 	d("fix EvaluationMethod(s)", "green");
// 	DB::statement("TRUNCATE evaluation_methods_values;");
// 	$evaluationMethods = \App\Models\EvaluationMethod::all();
// 	foreach ($evaluationMethods as $evaluationMethod) {
// 		$evaluationMethod->save();
// 	}

// 	d("fix AssessmentEvaluation(s)", "green");
// 	$assessmentEvaluations = \App\Models\AssessmentEvaluation::all();
// 	foreach ($assessmentEvaluations as $assessmentEvaluation) {
// 		$assessmentEvaluation->save();
// 	}

// 	d("fix GoalEvaluation(s)", "green");
// 	$goalsEvaluations = \App\Models\GoalEvaluation::all();
// 	foreach ($goalsEvaluations as $goalEvaluation) {
// 		$goalEvaluation->save();
// 	}

// 	d("fix Goal(s)", "green");
// 	$goals = \App\Models\Goal::all();
// 	foreach ($goals as $goal) {
// 		$goal->save();
// 	}
	
// 	dd("done");
// });

// Route::get('/test', function (Request $request) {

//     $request->fetch = 'fetch_employees';
//     $employees = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $request->fetch = 'fetch_users_count';
    
//     $request->like_roles = ['specialist'];
//     $nSpecialists = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $request->like_roles = ['teacher'];
//     $nTeachers = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $request->like_roles = ['specialist'];
//     $request->nationality = 'SA';
//     $nSASpecialists = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $request->like_roles = ['teacher'];
//     $request->nationality = 'SA';
//     $nSATeachers = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $request->nationality = null;
//     $request->like_roles = null;
//     $request->exclude_roles = ['admin', 'parent'];
//     $request->not_like_roles = ['specialist', 'teacher'];
//     $nRoles = (new \App\Http\Controllers\Admin\UserController)->fetchRolesUsers($request);

//     $plan = App\Models\OperationalPlan::find(1);
    
//     app()->setLocale('ar');
//     $departments = App\Models\OperationalPlanGoal::departments();

//     $data = [
//         'plan' => $plan,
//         'information' => json_decode($plan->information, true),
//         'departments' => $departments,
//         'employees' => json_decode($employees->content(), true)['data'],
//         'specialists' => json_decode($nSpecialists->content(), true)['data'],
//         'teachers' => json_decode($nTeachers->content(), true)['data'],
//         'sa_specialists' => json_decode($nSASpecialists->content(), true)['data'],
//         'sa_teachers' => json_decode($nSATeachers->content(), true)['data'],
//         'roles' => json_decode($nRoles->content(), true)['data']
//     ];
//     //dd($data);
//     $config = [
//         'format' => 'A4-L' // Landscape
//     ];
//     //$reportHtml = view()->render();

//     $pdf = \PDF::loadView('pdf.operational_plan',  compact('data'), [], $config);

//     return $pdf->stream('document.pdf');

// });

Route::group(['middleware' => ['auth:sanctum', 'auth.relations']], function () {

    Route::prefix('account')->group(function () {
        Route::get('/profile', [App\Http\Controllers\Account\ProfileController::class, 'show']);
        Route::post('/profile', [App\Http\Controllers\Account\ProfileController::class, 'update']);
        Route::put('/password', [App\Http\Controllers\Account\ProfileController::class, 'updatePassword']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'index']);
        Route::post('/import', [App\Http\Controllers\Admin\UserController::class, 'import']);
        Route::get('/{user}/show', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('user.show');
        Route::post('/{user}/files', [App\Http\Controllers\Admin\UserController::class, 'files'])->name('user.files');
        Route::post('/{user}/add_file', [App\Http\Controllers\Admin\UserController::class, 'addFile'])->name('user.add_file');
        Route::post('/{user}/update_file', [App\Http\Controllers\Admin\UserController::class, 'updateFile'])->name('user.update_file');
        Route::post('/{user}/delete_file', [App\Http\Controllers\Admin\UserController::class, 'deleteFile']);
        Route::post('/create', [App\Http\Controllers\Admin\UserController::class, 'create'])->name('user.create');
        Route::post('/update/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('user.update');
        Route::put('/change-password/{user}', [App\Http\Controllers\Admin\UserController::class, 'change_password'])->name('user.change_password');
        Route::post('/impersonate/{user}', [App\Http\Controllers\Admin\UserController::class, 'impersonate'])->name('user.impersonate');
        Route::post('/stop-impersonation', [App\Http\Controllers\Admin\UserController::class, 'stop_impersonation'])->name('user.stop_impersonation');
        Route::delete('/{id}', [App\Http\Controllers\Admin\UserController::class, 'delete']);
        Route::get('/fetch_image/{id}', [App\Http\Controllers\Admin\UserController::class, 'fetchImage']);
        Route::delete('/delete_image/{id}', [App\Http\Controllers\Admin\UserController::class, 'deleteImage']);
        Route::patch('/{id}', [App\Http\Controllers\Admin\UserController::class, 'restore']);
        Route::get('/select-items', [App\Http\Controllers\Admin\UserController::class, 'selectItems']);
        Route::get('/search-items', [App\Http\Controllers\Admin\UserController::class, 'searchItems']);
        Route::get('/fetch_roles_users', [App\Http\Controllers\Admin\UserController::class, 'fetchRolesUsers']);

        Route::get('/{user}/phone-numbers', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'index'])->whereNumber('user')->name('user.phone_numbers.index');
        Route::post('/{user}/phone-numbers', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'store'])->whereNumber('user')->name('user.phone_numbers.store');
        Route::put('/{user}/phone-numbers/{phoneNumber}', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'update'])->whereNumber(['user', 'phoneNumber'])->name('user.phone_numbers.update');
        Route::delete('/{user}/phone-numbers/{phoneNumber}', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'destroy'])->whereNumber(['user', 'phoneNumber'])->name('user.phone_numbers.destroy');
        Route::post('/{user}/phone-numbers/{phoneNumber}/primary', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'primary'])->whereNumber(['user', 'phoneNumber'])->name('user.phone_numbers.primary');
        Route::post('/{user}/phone-numbers/{phoneNumber}/verify', [App\Http\Controllers\Admin\UserPhoneNumberController::class, 'verify'])->whereNumber(['user', 'phoneNumber'])->name('user.phone_numbers.verify');

    });
    
    Route::prefix('services')->group(function () {
        Route::get('/', [App\Http\Controllers\General\ServiceController::class, 'index']);
    });

    Route::prefix('terms')->group(function () {
        Route::get('/', [App\Http\Controllers\General\TermController::class, 'index']);
        Route::get('/current', [App\Http\Controllers\General\TermController::class, 'currentTerm']);
        Route::get('/items', [App\Http\Controllers\General\TermController::class, 'filterItems']);
        Route::get('/{term}/show', [App\Http\Controllers\General\TermController::class, 'show'])->name('term.show');
        Route::post('/put', [App\Http\Controllers\General\TermController::class, 'put'])->name('term.put');
        Route::delete('/{id}', [App\Http\Controllers\General\TermController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\TermController::class, 'restore']);
    });
    
    Route::prefix('disabilities')->group(function () {
        Route::get('/', [App\Http\Controllers\General\DisabilityController::class, 'index']);
        Route::post('/put', [App\Http\Controllers\General\DisabilityController::class, 'put'])->name('disability.put');
    });

    Route::prefix('cases')->group(function () {
        Route::get('/', [App\Http\Controllers\General\SCaseController::class, 'index'])->middleware(['permission:'.App\Support\CaseListAccess::listPermissionMiddleware()]);
        Route::get('/select-items', [App\Http\Controllers\General\SCaseController::class, 'selectItems'])->middleware(['permission:'.App\Support\CaseListAccess::selectItemsPermissionMiddleware()]);
        Route::get('/assigned-staff', [App\Http\Controllers\General\SCaseController::class, 'assignedStaff'])->middleware(['permission:'.App\Support\CaseListAccess::allScopePermissionMiddleware()]);
        Route::post('/import', [App\Http\Controllers\General\SCaseController::class, 'import']);
        Route::get('/{case}/show', [App\Http\Controllers\General\SCaseController::class, 'show'])->name('case.show');
        Route::post('/{case}/files', [App\Http\Controllers\General\SCaseController::class, 'files'])->name('case.files');
        Route::get('/{case}/pdf/{pdfType}', [App\Http\Controllers\General\SCaseController::class, 'pdf'])->name('case.pdf');
        Route::post('/{case}/add_file', [App\Http\Controllers\General\SCaseController::class, 'addFile'])->name('case.add_file');
        Route::post('/{case}/delete_file', [App\Http\Controllers\General\SCaseController::class, 'deleteFile']);
        Route::post('/create', [App\Http\Controllers\General\SCaseController::class, 'put'])->name('case.create');
        Route::post('/{case}/update', [App\Http\Controllers\General\SCaseController::class, 'put'])->name('case.update');
        Route::delete('/{id}', [App\Http\Controllers\General\SCaseController::class, 'delete'])->middleware(['permission:admin_cases']);
        Route::get('/fetch_image/{case}', [App\Http\Controllers\General\SCaseController::class, 'fetchImage']);
        Route::delete('/delete_image/{id}', [App\Http\Controllers\General\SCaseController::class, 'deleteImage']);
        Route::patch('/{id}', [App\Http\Controllers\General\SCaseController::class, 'restore'])->middleware(['permission:admin_cases']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('get/all', [App\Http\Controllers\Admin\RolesController::class, 'getRoles']);
        Route::get('', [App\Http\Controllers\Admin\RolesController::class, 'roles']);
        Route::put('user/{user}', [App\Http\Controllers\Admin\RolesController::class, 'sync']);
        Route::get('user/{user}', [App\Http\Controllers\Admin\RolesController::class, 'user']);
        Route::get('{role}', [App\Http\Controllers\Admin\RolesController::class, 'get']);
        Route::put('{role?}', [App\Http\Controllers\Admin\RolesController::class, 'put']);
        Route::delete('{role}', [App\Http\Controllers\Admin\RolesController::class, 'delete']);
    });

    Route::prefix('assessments')->group(function () {
        Route::get('/', [App\Http\Controllers\General\AssessmentController::class, 'index']);
        Route::post('/import', [App\Http\Controllers\General\AssessmentController::class, 'import'])->middleware(['module:scales']);
        Route::put('/put', [App\Http\Controllers\General\AssessmentController::class, 'put'])->middleware(['module:scales'])->name('assessment.create');
        Route::put('/put/{assessment}', [App\Http\Controllers\General\AssessmentController::class, 'put'])->middleware(['module:scales'])->name('assessment.update');
        Route::get('/evaluation-methods', [App\Http\Controllers\General\AssessmentController::class, 'evaluationMethods'])->name('assessment.evaluation_methods');
        Route::delete('/{assessment}', [App\Http\Controllers\General\AssessmentController::class, 'delete'])->middleware(['module:scales']);
        Route::patch('/{assessment}', [App\Http\Controllers\General\AssessmentController::class, 'restore'])->middleware(['module:scales']);
    });

    Route::prefix('assessment-evaluations')->group(function () {
        Route::get('/', [App\Http\Controllers\General\AssessmentEvaluationController::class, 'index']);
        Route::post('/put', [App\Http\Controllers\General\AssessmentEvaluationController::class, 'put']);
        Route::post('/list-weaks', [App\Http\Controllers\General\AssessmentEvaluationController::class, 'list_assessment_evaluation']);
        Route::post('/move-weaks', [App\Http\Controllers\General\AssessmentEvaluationController::class, 'add_weaks']);
    });

    Route::prefix('meeting-rooms')->group(function () {
        Route::get('/', [App\Http\Controllers\General\MeetingRoomController::class, 'index']);
        Route::post('/invitations/redeem', [App\Http\Controllers\General\MeetingRoomController::class, 'redeemInvitation']);
        Route::get('/{room}/invitation-link', [App\Http\Controllers\General\MeetingRoomController::class, 'invitationLink']);
        Route::post('/{room}/attendance/join', [App\Http\Controllers\General\MeetingRoomController::class, 'attendanceJoin']);
        Route::post('/{room}/attendance/leave', [App\Http\Controllers\General\MeetingRoomController::class, 'attendanceLeave']);
        Route::get('/{room}/attendance', [App\Http\Controllers\General\MeetingRoomController::class, 'attendance']);
        Route::get('/{room}/show', [App\Http\Controllers\General\MeetingRoomController::class, 'show']);
        Route::put('/put/{room}', [App\Http\Controllers\General\MeetingRoomController::class, 'put']);
        Route::delete('/{id}', [App\Http\Controllers\General\MeetingRoomController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\MeetingRoomController::class, 'restore']);
        Route::post('/start_now', [App\Http\Controllers\General\MeetingRoomController::class, 'start_meeting_meesage'])->name('meeting.start_now');
        Route::post('/start_later', [App\Http\Controllers\General\MeetingRoomController::class, 'start_meeting_meesage'])->name('meeting.start_later');
    });

    Route::prefix('goals')->group(function () {
        Route::get('/', [App\Http\Controllers\General\GoalController::class, 'index']);
        Route::get('/select-items', [App\Http\Controllers\General\GoalController::class, 'selectItems']);
        Route::get('/select-case-items', [App\Http\Controllers\General\GoalController::class, 'selectCaseItems']);
        Route::get('/show/{goal}', [App\Http\Controllers\General\GoalController::class, 'show'])->name('goal.show');
        Route::get('/parent-details/{goal}', [App\Http\Controllers\General\GoalController::class, 'parentDetails']);
        Route::post('/parent-details/{goal}/messages', [App\Http\Controllers\General\GoalController::class, 'parentAddMessage']);
        Route::post('/parent-details/{goal}/messages/chunks', [App\Http\Controllers\General\GoalController::class, 'parentAddMessageChunk']);
        Route::post('/transfer_goal/{goal}', [App\Http\Controllers\General\GoalController::class, 'transferGoal']);
        Route::get('/period_status', [App\Http\Controllers\General\GoalController::class, 'periodStatus']);
        Route::post('/period_action', [App\Http\Controllers\General\GoalController::class, 'periodAction']);
        Route::put('/put/{goal}', [App\Http\Controllers\General\GoalController::class, 'put']);
        Route::delete('/{id}', [App\Http\Controllers\General\GoalController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\GoalController::class, 'restore']);
        Route::get('/filter', [App\Http\Controllers\General\GoalController::class, 'filter']);
        Route::get('/parent-treatment-categories', [App\Http\Controllers\General\GoalController::class, 'parentTreatmentCategories']);
        Route::put('/update_eval', [App\Http\Controllers\General\GoalController::class, 'update_evaluation']);
        Route::put('/start_session/{goal}', [App\Http\Controllers\General\GoalController::class, 'start_session']);
        Route::put('/end_session/{goal}', [App\Http\Controllers\General\GoalController::class, 'end_session']);

        Route::get('/steps', [App\Http\Controllers\General\GoalsStepsController::class, 'index']);
        Route::put('/steps/put/{goal?}', [App\Http\Controllers\General\GoalsStepsController::class, 'put']);
        Route::delete('/steps/{id}', [App\Http\Controllers\General\GoalsStepsController::class, 'delete']);
        Route::patch('/steps/{id}', [App\Http\Controllers\General\GoalsStepsController::class, 'restore']);
        Route::put('/steps/reorder/{step}', [App\Http\Controllers\General\GoalsStepsController::class, 'reOrder']);

        Route::get('/evaluations_steps', [App\Http\Controllers\General\GoalController::class, 'evaluations']);
        Route::put('/evaluations_steps/put', [App\Http\Controllers\General\GoalController::class, 'putEvaluationStep']);
        Route::delete('/evaluations_steps/{id}', [App\Http\Controllers\General\GoalController::class, 'deleteEvaluationStep']);

    });

    Route::prefix('attendances')->group(function () {
        Route::get('/', [App\Http\Controllers\General\AttendanceController::class, 'index']);
        Route::get('/export', [App\Http\Controllers\General\AttendanceController::class, 'export']);
        Route::get('/case', [App\Http\Controllers\General\AttendanceController::class, 'index_for_case']);
        Route::post('/put/{id}', [App\Http\Controllers\General\AttendanceController::class, 'put']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\General\UserNotificationController::class, 'index']);
        Route::post('/{id}/read', [App\Http\Controllers\General\UserNotificationController::class, 'markRead']);
    });

    Route::prefix('messages')->group(function () {
        Route::get('/', [App\Http\Controllers\General\MessageController::class, 'index']);
        Route::post('/add', [App\Http\Controllers\General\MessageController::class, 'put']);
        Route::delete('/{id}', [App\Http\Controllers\General\MessageController::class, 'delete']);
        Route::get('/file/{message}', [App\Http\Controllers\General\MessageController::class, 'file']);
    });

    Route::prefix('center-activities')->group(function () {
        Route::get('/', [App\Http\Controllers\General\CenterActivityController::class, 'index']);
        Route::get('/parent-feed', [App\Http\Controllers\General\CenterActivityEntryController::class, 'parentFeed']);
        Route::get('/{activity}/show', [App\Http\Controllers\General\CenterActivityController::class, 'show']);
        Route::put('/put/{activity?}', [App\Http\Controllers\General\CenterActivityController::class, 'put']);
        Route::delete('/{id}', [App\Http\Controllers\General\CenterActivityController::class, 'delete']);
        Route::get('/{activity}/entries', [App\Http\Controllers\General\CenterActivityEntryController::class, 'index']);
        Route::post('/{activity}/entries/add', [App\Http\Controllers\General\CenterActivityEntryController::class, 'put']);
        Route::delete('/entries/{entry}', [App\Http\Controllers\General\CenterActivityEntryController::class, 'delete']);
        Route::get('/entries/file/{entry}', [App\Http\Controllers\General\CenterActivityEntryController::class, 'file']);
    });

    Route::prefix('questionnaires')->middleware(['module:questionnaires'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\QuestionnaireController::class, 'index']);
        Route::post('/put', [App\Http\Controllers\General\QuestionnaireController::class, 'put'])->name('questionnaire.put');
        Route::post('/put-custom', [App\Http\Controllers\General\QuestionnaireController::class, 'putCustom'])->name('questionnaire.put_custom');
        Route::get('/task', [App\Http\Controllers\General\QuestionnaireController::class, 'questionnaireTask']);
        Route::post('/task', [App\Http\Controllers\General\QuestionnaireController::class, 'questionnaireTask']);
        Route::get('/respondent/tasks', [App\Http\Controllers\General\QuestionnaireController::class, 'respondentAvailableTasks']);
        Route::get('/target-audience/roles', [App\Http\Controllers\General\QuestionnaireController::class, 'targetAudienceRoles']);
        Route::delete('/{id}', [App\Http\Controllers\General\QuestionnaireController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\QuestionnaireController::class, 'restore']);
        Route::get('/{task}/show', [App\Http\Controllers\General\QuestionnaireController::class, 'show'])->name('questionnaire.show');
        Route::post('/fetch/questions_with_answers', [App\Http\Controllers\General\QuestionnaireController::class, 'fetchQuestionsWithAnswers'])->name('questionnaire.fetch_questions_with_answers');
        Route::post('/{task}/analytics', [App\Http\Controllers\General\QuestionnaireController::class, 'questionnaireAnalytics'])->name('questionnaire.analytics');
        Route::get('/{task}/respondents/search', [App\Http\Controllers\General\QuestionnaireController::class, 'searchQuestionnaireRespondents'])->name('questionnaire.respondents_search');
        Route::get('/{task}/export/responses', [App\Http\Controllers\General\QuestionnaireController::class, 'exportQuestionnaireResponses'])->name('questionnaire.export_responses');
        Route::get('/{task}/export/analytics-pdf', [App\Http\Controllers\General\QuestionnaireController::class, 'exportQuestionnaireAnalyticsPdf'])->name('questionnaire.export_analytics_pdf');
        Route::get('/{task}/export/template-pdf', [App\Http\Controllers\General\QuestionnaireController::class, 'exportQuestionnaireTemplatePdf'])->name('questionnaire.export_template_pdf');
        Route::post('/{task}/copy-questions/preview', [App\Http\Controllers\General\QuestionnaireController::class, 'copyQuestionsPreview'])->whereNumber('task')->name('questionnaire.copy_questions_preview');
        Route::post('/{task}/copy-questions', [App\Http\Controllers\General\QuestionnaireController::class, 'copyQuestions'])->whereNumber('task')->name('questionnaire.copy_questions');
        Route::post('/{task}/fetch/questions_with_user_answer', [App\Http\Controllers\General\QuestionnaireController::class, 'fetchQuestions'])->name('questionnaire.fetch_questions');
        Route::post('/{task}/add_user_answers', [App\Http\Controllers\General\QuestionnaireController::class, 'addAnswers'])->name('questionnaire.add_answers');
    });

    Route::prefix('payments')->middleware(['module:study-fees'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\SCaseController::class, 'payments']);
        Route::get('/{payment}/show', [App\Http\Controllers\General\SCaseController::class, 'showPayment'])->name('term.show_payment');
        Route::post('/put', [App\Http\Controllers\General\SCaseController::class, 'putPayment'])->name('term.put_payment');
        Route::post('/{payment}/action', [App\Http\Controllers\General\SCaseController::class, 'actionPayment']);
        Route::delete('/{id}', [App\Http\Controllers\General\SCaseController::class, 'deletePayment']);
        Route::get('/fetch_file/{payment}', [App\Http\Controllers\General\SCaseController::class, 'fetchPaymentFile']);
    });

    Route::prefix('fees')->middleware(['module:study-fees'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\SCaseController::class, 'fees']);
        Route::get('/items', [App\Http\Controllers\General\SCaseController::class, 'feesItems']);
        Route::get('/{fee}/show', [App\Http\Controllers\General\SCaseController::class, 'showFee'])->name('term.show_fee');
        Route::post('/put', [App\Http\Controllers\General\SCaseController::class, 'putFee'])->name('term.put_fee');
        Route::delete('/{id}', [App\Http\Controllers\General\SCaseController::class, 'deleteFee']);
        Route::patch('/{id}', [App\Http\Controllers\General\SCaseController::class, 'restoreFee']);
    });

    Route::prefix('plans')->middleware(['module:operation-plans'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\OperationalPlanController::class, 'index']);
        Route::get('/pdf/{plan}', [App\Http\Controllers\General\OperationalPlanController::class, 'pdfPlan']);
        Route::get('/show/{plan}', [App\Http\Controllers\General\OperationalPlanController::class, 'show'])->name('plan.show');
        Route::post('/put/{plan}', [App\Http\Controllers\General\OperationalPlanController::class, 'put'])->name('plan.put');
        Route::delete('/{id}', [App\Http\Controllers\General\OperationalPlanController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\OperationalPlanController::class, 'restore']);
        Route::get('/goals', [App\Http\Controllers\General\OperationalPlanController::class, 'goals']);
        Route::get('/goal/show/{goal}', [App\Http\Controllers\General\OperationalPlanController::class, 'showGoal'])->name('plan.show_goal');
        Route::post('/goal/put/{goal}', [App\Http\Controllers\General\OperationalPlanController::class, 'putGoal'])->name('plan.put_goal');
        Route::post('/goal/action/{goal}', [App\Http\Controllers\General\OperationalPlanController::class, 'actionGoal'])->name('plan.action_goal');
        Route::delete('/goal/{id}', [App\Http\Controllers\General\OperationalPlanController::class, 'deleteGoal']);
    });

    Route::prefix('employees_attendance')->group(function () {
        Route::get('/', [App\Http\Controllers\General\EmployeeAttendanceController::class, 'index']);
        Route::post('/put', [App\Http\Controllers\General\EmployeeAttendanceController::class, 'put'])->name('employee_attendance.put');
        Route::get('/show/{attendance}', [App\Http\Controllers\General\EmployeeAttendanceController::class, 'show'])->name('employee_attendance.show');
    });

    Route::prefix('employee_leaves')->group(function () {
        Route::get('/', [App\Http\Controllers\General\EmployeeLeaveController::class, 'index']);
        Route::get('/balance', [App\Http\Controllers\General\EmployeeLeaveController::class, 'balance']);
        Route::post('/put', [App\Http\Controllers\General\EmployeeLeaveController::class, 'put']);
        Route::post('/{id}/review', [App\Http\Controllers\General\EmployeeLeaveController::class, 'review']);
        Route::delete('/{id}', [App\Http\Controllers\General\EmployeeLeaveController::class, 'delete']);
    });

    Route::prefix('hr')->group(function () {
        Route::get('/lookups', [App\Http\Controllers\Hr\LookupController::class, 'index'])
            ->middleware(['module:hr-organization,hr-employees,hr-settings,hr-approvals,hr-audit,hr-compensation,hr-documents,hr-attendance-setup,hr-attendance-config,hr-location-monitoring,hr-attendance-records,hr-attendance-processing,hr-leave,hr-additional-days']);

        Route::middleware(['module:hr-organization'])->group(function () {
            Route::get('/branches', [App\Http\Controllers\Hr\BranchController::class, 'index']);
            Route::get('/branches/cities', [App\Http\Controllers\Hr\BranchController::class, 'cities']);
            Route::post('/branches', [App\Http\Controllers\Hr\BranchController::class, 'store']);
            Route::get('/branches/{branch}', [App\Http\Controllers\Hr\BranchController::class, 'show'])->whereNumber('branch');
            Route::put('/branches/{branch}', [App\Http\Controllers\Hr\BranchController::class, 'update'])->whereNumber('branch');
            Route::get('/branches/{branch}/employees', [App\Http\Controllers\Hr\BranchController::class, 'employees'])->whereNumber('branch');
            // PATCH / DELETE: require admin_hr-organization.
            Route::patch('/branches/{branch}/activate', [App\Http\Controllers\Hr\BranchController::class, 'activate'])->whereNumber('branch');
            Route::patch('/branches/{branch}/deactivate', [App\Http\Controllers\Hr\BranchController::class, 'deactivate'])->whereNumber('branch');
            Route::delete('/branches/{branch}', [App\Http\Controllers\Hr\BranchController::class, 'destroy'])->whereNumber('branch');
            Route::patch('/branches/{branch}/restore', [App\Http\Controllers\Hr\BranchController::class, 'restore'])->whereNumber('branch');

            Route::get('/work-locations', [App\Http\Controllers\Hr\WorkLocationController::class, 'index']);
            Route::get('/work-locations/map', [App\Http\Controllers\Hr\WorkLocationController::class, 'map']);
            Route::post('/work-locations', [App\Http\Controllers\Hr\WorkLocationController::class, 'store']);
            Route::get('/work-locations/{workLocation}', [App\Http\Controllers\Hr\WorkLocationController::class, 'show'])->whereNumber('workLocation');
            Route::put('/work-locations/{workLocation}', [App\Http\Controllers\Hr\WorkLocationController::class, 'update'])->whereNumber('workLocation');
            Route::get('/work-locations/{workLocation}/employees', [App\Http\Controllers\Hr\WorkLocationController::class, 'employees'])->whereNumber('workLocation');
            Route::patch('/work-locations/{workLocation}/activate', [App\Http\Controllers\Hr\WorkLocationController::class, 'activate'])->whereNumber('workLocation');
            Route::patch('/work-locations/{workLocation}/deactivate', [App\Http\Controllers\Hr\WorkLocationController::class, 'deactivate'])->whereNumber('workLocation');
            Route::delete('/work-locations/{workLocation}', [App\Http\Controllers\Hr\WorkLocationController::class, 'destroy'])->whereNumber('workLocation');
            Route::patch('/work-locations/{workLocation}/restore', [App\Http\Controllers\Hr\WorkLocationController::class, 'restore'])->whereNumber('workLocation');

            Route::get('/departments', [App\Http\Controllers\Hr\DepartmentController::class, 'index']);
            Route::post('/departments', [App\Http\Controllers\Hr\DepartmentController::class, 'store']);
            Route::get('/departments/{department}', [App\Http\Controllers\Hr\DepartmentController::class, 'show'])->whereNumber('department');
            Route::put('/departments/{department}', [App\Http\Controllers\Hr\DepartmentController::class, 'update'])->whereNumber('department');
            Route::delete('/departments/{department}', [App\Http\Controllers\Hr\DepartmentController::class, 'destroy'])->whereNumber('department');
            Route::patch('/departments/{department}/restore', [App\Http\Controllers\Hr\DepartmentController::class, 'restore'])->whereNumber('department');
        });

        Route::middleware(['module:hr-employees'])->group(function () {
            Route::get('/employees', [App\Http\Controllers\Hr\EmployeeController::class, 'index']);
            Route::get('/employees/candidates', [App\Http\Controllers\Hr\EmployeeController::class, 'candidates']);
            Route::get('/employees/job-titles', [App\Http\Controllers\Hr\EmployeeController::class, 'jobTitles']);
            Route::post('/employees', [App\Http\Controllers\Hr\EmployeeController::class, 'store']);
            Route::get('/employees/{employee}', [App\Http\Controllers\Hr\EmployeeController::class, 'show'])->whereNumber('employee');
            Route::put('/employees/{employee}', [App\Http\Controllers\Hr\EmployeeController::class, 'update'])->whereNumber('employee');
            Route::get('/employees/{employee}/attendance-configs', [App\Http\Controllers\Hr\EmployeeAttendanceController::class, 'index'])->whereNumber('employee');
            Route::get('/employees/{employee}/attendance-rules', [App\Http\Controllers\Hr\EmployeeAttendanceController::class, 'rules'])->whereNumber('employee');
            Route::get('/employees/{employee}/attendance-records', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'records'])->whereNumber('employee');
            Route::get('/employees/{employee}/attendance-punches', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'punches'])->whereNumber('employee');
            Route::post('/employees/{employee}/photo', [App\Http\Controllers\Hr\EmployeeController::class, 'updatePhoto'])->whereNumber('employee');
            Route::post('/employees/{employee}/photo/remove', [App\Http\Controllers\Hr\EmployeeController::class, 'removePhoto'])->whereNumber('employee');
            Route::get('/employees/{employee}/history', [App\Http\Controllers\Hr\EmployeeController::class, 'history'])->whereNumber('employee');
            Route::get('/employees/{employee}/shifts', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'shifts'])->whereNumber('employee');
            Route::post('/employees/{employee}/shifts', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'storeShift'])->whereNumber('employee');
            Route::post('/employees/{employee}/shifts/{assignment}/cancel', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'cancelShift'])->whereNumber(['employee', 'assignment']);
            Route::get('/employees/{employee}/schedule', [App\Http\Controllers\Hr\ShiftScheduleController::class, 'employee'])->whereNumber('employee');
            Route::get('/employees/{employee}/work-assignments', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'workAssignments'])->whereNumber('employee');
            Route::post('/employees/{employee}/work-assignments', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'storeWorkAssignment'])->whereNumber('employee');
            Route::post('/employees/{employee}/work-assignments/{assignment}/cancel', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'cancelWorkAssignment'])->whereNumber(['employee', 'assignment']);
            // PATCH: requires admin_hr-employees.
            Route::patch('/employees/{employee}/deactivate', [App\Http\Controllers\Hr\EmployeeController::class, 'deactivate'])->whereNumber('employee')->name('hr.employees.deactivate');
            Route::patch('/employees/{employee}/activate', [App\Http\Controllers\Hr\EmployeeController::class, 'activate'])->whereNumber('employee')->name('hr.employees.activate');
        });

        // Changing attendance configuration requires edit_hr-attendance-config (viewing it: hr-employees above).
        Route::middleware(['module:hr-employees', 'module:hr-attendance-config'])->group(function () {
            Route::put('/employees/{employee}/attendance', [App\Http\Controllers\Hr\EmployeeAttendanceController::class, 'update'])->whereNumber('employee');
            Route::post('/employees/{employee}/attendance-configs/{config}/cancel', [App\Http\Controllers\Hr\EmployeeAttendanceController::class, 'cancel'])->whereNumber(['employee', 'config']);
        });

        Route::middleware(['module:hr-compensation'])->group(function () {
            Route::get('/employees/payroll-eligibility', [App\Http\Controllers\Hr\EmployeeController::class, 'payrollEligibility']);
            Route::get('/employees/{employee}/salaries', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'salaries'])->whereNumber('employee');
            Route::post('/employees/{employee}/salaries', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'storeSalary'])->whereNumber('employee');
            Route::post('/employees/{employee}/salaries/{salary}/cancel', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'cancelSalary'])->whereNumber(['employee', 'salary']);
            Route::get('/employees/{employee}/insurances', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'insurances'])->whereNumber('employee');
            Route::post('/employees/{employee}/insurances', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'storeInsurance'])->whereNumber('employee');
            Route::post('/employees/{employee}/insurances/{insurance}/cancel', [App\Http\Controllers\Hr\EmployeeRecordController::class, 'cancelInsurance'])->whereNumber(['employee', 'insurance']);
        });

        Route::middleware(['module:hr-documents'])->group(function () {
            Route::get('/employees/{employee}/documents', [App\Http\Controllers\Hr\EmployeeDocumentController::class, 'index'])->whereNumber('employee');
            Route::post('/employees/{employee}/documents', [App\Http\Controllers\Hr\EmployeeDocumentController::class, 'store'])->whereNumber('employee');
            Route::put('/employees/{employee}/documents/{document}', [App\Http\Controllers\Hr\EmployeeDocumentController::class, 'update'])->whereNumber(['employee', 'document']);
            Route::get('/employees/{employee}/documents/{document}/download', [App\Http\Controllers\Hr\EmployeeDocumentController::class, 'download'])->whereNumber(['employee', 'document']);
            Route::delete('/employees/{employee}/documents/{document}', [App\Http\Controllers\Hr\EmployeeDocumentController::class, 'destroy'])->whereNumber(['employee', 'document']);
        });

        Route::middleware(['module:hr-attendance-setup'])->group(function () {
            Route::get('/shifts', [App\Http\Controllers\Hr\ShiftController::class, 'index']);
            Route::post('/shifts', [App\Http\Controllers\Hr\ShiftController::class, 'store']);
            Route::get('/shifts/{shift}', [App\Http\Controllers\Hr\ShiftController::class, 'show'])->whereNumber('shift');
            Route::put('/shifts/{shift}', [App\Http\Controllers\Hr\ShiftController::class, 'update'])->whereNumber('shift');
            Route::get('/shifts/{shift}/versions', [App\Http\Controllers\Hr\ShiftController::class, 'versions'])->whereNumber('shift');
            Route::post('/shifts/{shift}/versions/{version}/cancel', [App\Http\Controllers\Hr\ShiftController::class, 'cancelVersion'])->whereNumber(['shift', 'version']);
            // Also requires edit_hr-employees (checked in the controller).
            Route::post('/shifts/{shift}/assign', [App\Http\Controllers\Hr\ShiftController::class, 'assign'])->whereNumber('shift');
            // PATCH / DELETE: require admin_hr-attendance-setup.
            Route::patch('/shifts/{shift}/activate', [App\Http\Controllers\Hr\ShiftController::class, 'activate'])->whereNumber('shift');
            Route::patch('/shifts/{shift}/deactivate', [App\Http\Controllers\Hr\ShiftController::class, 'deactivate'])->whereNumber('shift');
            Route::delete('/shifts/{shift}', [App\Http\Controllers\Hr\ShiftController::class, 'destroy'])->whereNumber('shift');
            Route::patch('/shifts/{shift}/restore', [App\Http\Controllers\Hr\ShiftController::class, 'restore'])->whereNumber('shift');

            Route::get('/shift-schedule', [App\Http\Controllers\Hr\ShiftScheduleController::class, 'index']);
            Route::get('/shift-exceptions', [App\Http\Controllers\Hr\ShiftExceptionController::class, 'index']);
            Route::post('/shift-exceptions', [App\Http\Controllers\Hr\ShiftExceptionController::class, 'store']);
            Route::post('/shift-exceptions/{exception}/cancel', [App\Http\Controllers\Hr\ShiftExceptionController::class, 'cancel'])->whereNumber('exception');

            Route::get('/attendance-policies', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'index']);
            Route::post('/attendance-policies', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'store']);
            Route::get('/attendance-policies/{policy}', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'show'])->whereNumber('policy');
            Route::put('/attendance-policies/{policy}', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'update'])->whereNumber('policy');
            Route::get('/attendance-policies/{policy}/versions', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'versions'])->whereNumber('policy');
            Route::post('/attendance-policies/{policy}/versions/{version}/cancel', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'cancelVersion'])->whereNumber(['policy', 'version']);
            Route::get('/attendance-policies/{policy}/employees', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'employees'])->whereNumber('policy');
            // PATCH / DELETE: require admin_hr-attendance-setup.
            Route::patch('/attendance-policies/{policy}/activate', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'activate'])->whereNumber('policy');
            Route::patch('/attendance-policies/{policy}/deactivate', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'deactivate'])->whereNumber('policy');
            Route::delete('/attendance-policies/{policy}', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'destroy'])->whereNumber('policy');
            Route::patch('/attendance-policies/{policy}/restore', [App\Http\Controllers\Hr\AttendancePolicyController::class, 'restore'])->whereNumber('policy');

            // Official / company holidays. DELETE requires admin_hr-attendance-setup.
            Route::get('/holidays', [App\Http\Controllers\Hr\HolidayController::class, 'index']);
            Route::post('/holidays', [App\Http\Controllers\Hr\HolidayController::class, 'store']);
            Route::put('/holidays/{holiday}', [App\Http\Controllers\Hr\HolidayController::class, 'update'])->whereNumber('holiday');
            Route::delete('/holidays/{holiday}', [App\Http\Controllers\Hr\HolidayController::class, 'destroy'])->whereNumber('holiday');
        });

        Route::middleware(['module:hr-settings'])->group(function () {
            Route::get('/settings', [App\Http\Controllers\Hr\SettingController::class, 'index']);
            Route::put('/settings', [App\Http\Controllers\Hr\SettingController::class, 'update']);
        });

        // Electronic attendance of the signed-in user: own employee profile only, never another employee's.
        Route::prefix('my-attendance')->middleware(['module:hr-self-attendance'])->group(function () {
            Route::get('/', [App\Http\Controllers\Hr\MyAttendanceController::class, 'status']);
            Route::get('/history', [App\Http\Controllers\Hr\MyAttendanceController::class, 'history']);
            Route::post('/check-in', [App\Http\Controllers\Hr\MyAttendanceController::class, 'checkIn'])->middleware('throttle:20,1');
            Route::post('/check-out', [App\Http\Controllers\Hr\MyAttendanceController::class, 'checkOut'])->middleware('throttle:20,1');
            Route::post('/location-check', [App\Http\Controllers\Hr\MyAttendanceController::class, 'locationCheck'])->middleware('throttle:30,1');
        });

        // Future mobile application: device registration and location updates of the signed-in employee. Location
        // endpoints also require the device credentials (X-Device-Id, X-Device-Token).
        Route::prefix('mobile')->middleware(['module:hr-self-attendance'])->group(function () {
            Route::post('/devices', [App\Http\Controllers\Hr\MobileAttendanceController::class, 'register'])->middleware('throttle:10,1');
            Route::get('/device', [App\Http\Controllers\Hr\MobileAttendanceController::class, 'device'])->middleware('throttle:60,1');
            Route::post('/device/unregister', [App\Http\Controllers\Hr\MobileAttendanceController::class, 'unregister'])->middleware('throttle:10,1');
            Route::post('/locations', [App\Http\Controllers\Hr\MobileAttendanceController::class, 'locations'])->middleware('throttle:30,1');
        });

        // Central attendance records (electronic and manual). POST / PUT require edit_hr-attendance-records; history
        // limits, payroll lock and own-record rules are enforced in HrManualAttendance.
        Route::prefix('attendance-records')->middleware(['module:hr-attendance-records'])->group(function () {
            Route::get('/', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'index']);
            Route::get('/summary', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'summary']);
            Route::get('/employees', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'employees']);
            Route::get('/import-template', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'importTemplate']);
            Route::post('/preview', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'preview'])->middleware('throttle:120,1');
            Route::post('/bulk', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'bulk']);
            Route::post('/import', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'import'])->middleware('throttle:20,1');
            Route::post('/', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'store']);
            Route::get('/{record}', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'show'])->whereNumber('record');
            Route::put('/{record}', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'update'])->whereNumber('record');
            Route::get('/{record}/history', [App\Http\Controllers\Hr\AttendanceRecordController::class, 'history'])->whereNumber('record');
        });

        // Attendance processing and payroll attendance periods. POST / PUT require edit_hr-attendance-processing;
        // PATCH (approve / reopen) and DELETE require admin_hr-attendance-processing.
        Route::middleware(['module:hr-attendance-processing'])->group(function () {
            Route::get('/attendance-days', [App\Http\Controllers\Hr\AttendanceDayController::class, 'index']);
            Route::get('/attendance-days/summary', [App\Http\Controllers\Hr\AttendanceDayController::class, 'summary']);
            Route::get('/attendance-days/employees', [App\Http\Controllers\Hr\AttendanceDayController::class, 'employees']);
            Route::post('/attendance-days/process', [App\Http\Controllers\Hr\AttendanceDayController::class, 'process'])->middleware('throttle:30,1');
            Route::get('/attendance-days/{day}', [App\Http\Controllers\Hr\AttendanceDayController::class, 'show'])->whereNumber('day');
            Route::post('/attendance-days/{day}/correct', [App\Http\Controllers\Hr\AttendanceDayController::class, 'correct'])->whereNumber('day');
            Route::post('/attendance-days/{day}/reset', [App\Http\Controllers\Hr\AttendanceDayController::class, 'reset'])->whereNumber('day');

            Route::get('/attendance-periods', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'index']);
            Route::post('/attendance-periods', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'store']);
            Route::get('/attendance-periods/{period}', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'show'])->whereNumber('period');
            Route::put('/attendance-periods/{period}', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'update'])->whereNumber('period');
            Route::delete('/attendance-periods/{period}', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'destroy'])->whereNumber('period');
            Route::post('/attendance-periods/{period}/process', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'process'])->whereNumber('period');
            Route::patch('/attendance-periods/{period}/approve', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'approve'])->whereNumber('period');
            Route::patch('/attendance-periods/{period}/reopen', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'reopen'])->whereNumber('period');
            Route::get('/attendance-periods/{period}/summaries', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'summaries'])->whereNumber('period');
            Route::get('/attendance-periods/{period}/payroll', [App\Http\Controllers\Hr\AttendancePeriodController::class, 'payroll'])->whereNumber('period');
        });

        // Leave management. POST / PUT require edit_hr-leave (leave types, recorded leave and cancelling approved
        // leave: admin_hr-leave, checked in the controllers); DELETE requires admin_hr-leave.
        Route::middleware(['module:hr-leave'])->group(function () {
            Route::get('/leave-types', [App\Http\Controllers\Hr\LeaveTypeController::class, 'index']);
            Route::post('/leave-types', [App\Http\Controllers\Hr\LeaveTypeController::class, 'store']);
            Route::put('/leave-types/{leaveType}', [App\Http\Controllers\Hr\LeaveTypeController::class, 'update'])->whereNumber('leaveType');
            Route::delete('/leave-types/{leaveType}', [App\Http\Controllers\Hr\LeaveTypeController::class, 'destroy'])->whereNumber('leaveType');

            Route::get('/leave-requests', [App\Http\Controllers\Hr\LeaveRequestController::class, 'index']);
            Route::get('/leave-requests/employees', [App\Http\Controllers\Hr\LeaveRequestController::class, 'employees']);
            Route::post('/leave-requests/preview', [App\Http\Controllers\Hr\LeaveRequestController::class, 'preview'])->middleware('throttle:120,1');
            Route::post('/leave-requests', [App\Http\Controllers\Hr\LeaveRequestController::class, 'store']);
            Route::get('/leave-requests/{leaveRequest}', [App\Http\Controllers\Hr\LeaveRequestController::class, 'show'])->whereNumber('leaveRequest');
            Route::post('/leave-requests/{leaveRequest}/cancel', [App\Http\Controllers\Hr\LeaveRequestController::class, 'cancel'])->whereNumber('leaveRequest');

            Route::get('/leave-balances', [App\Http\Controllers\Hr\LeaveBalanceController::class, 'index']);
            Route::post('/leave-balances/adjust', [App\Http\Controllers\Hr\LeaveBalanceController::class, 'adjust']);
            Route::get('/leave-balances/{balance}/transactions', [App\Http\Controllers\Hr\LeaveBalanceController::class, 'transactions'])->whereNumber('balance');
        });

        // Additional working days (whole / half days only, no overtime hours). POST / PUT require
        // edit_hr-additional-days; cancelling an approved, unpaid record requires admin_hr-additional-days (checked in
        // the controller). Records are never deleted.
        Route::prefix('additional-days')->middleware(['module:hr-additional-days'])->group(function () {
            Route::get('/', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'index']);
            Route::get('/summary', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'summary']);
            Route::get('/employees', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'employees']);
            Route::get('/periods', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'periods']);
            Route::post('/', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'store']);
            Route::post('/submit', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'submitMany']);
            Route::get('/{record}', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'show'])->whereNumber('record');
            Route::put('/{record}', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'update'])->whereNumber('record');
            Route::get('/{record}/history', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'history'])->whereNumber('record');
            Route::post('/{record}/submit', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'submit'])->whereNumber('record');
            Route::post('/{record}/cancel', [App\Http\Controllers\Hr\AdditionalWorkDayController::class, 'cancel'])->whereNumber('record');
        });

        // Supporting documents: HR of the employee's branch, the employee, the requester and the approvers
        // (checked in the controller).
        Route::get('/leave-requests/{leaveRequest}/attachments/{attachment}', [App\Http\Controllers\Hr\LeaveRequestController::class, 'attachment'])->whereNumber(['leaveRequest', 'attachment']);

        // Leave of the signed-in user: own employee profile only.
        Route::prefix('my-leave')->middleware(['module:hr-self-leave'])->group(function () {
            Route::get('/', [App\Http\Controllers\Hr\MyLeaveController::class, 'overview']);
            Route::get('/requests', [App\Http\Controllers\Hr\MyLeaveController::class, 'requests']);
            Route::get('/requests/{leaveRequest}', [App\Http\Controllers\Hr\MyLeaveController::class, 'show'])->whereNumber('leaveRequest');
            Route::post('/preview', [App\Http\Controllers\Hr\MyLeaveController::class, 'preview'])->middleware('throttle:120,1');
            Route::post('/requests', [App\Http\Controllers\Hr\MyLeaveController::class, 'store'])->middleware('throttle:20,1');
            Route::post('/requests/{leaveRequest}/cancel', [App\Http\Controllers\Hr\MyLeaveController::class, 'cancel'])->whereNumber('leaveRequest');
        });

        Route::prefix('location-monitoring')->middleware(['module:hr-location-monitoring'])->group(function () {
            Route::get('/sessions', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'sessions']);
            Route::get('/employees', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'employees']);
            Route::get('/checks', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'checks']);
            Route::get('/exceptions', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'exceptions']);
            Route::post('/exceptions/{exception}/review', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'reviewException'])->whereNumber('exception');
            Route::get('/devices', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'devices']);
            Route::post('/devices/{device}/approve', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'approveDevice'])->whereNumber('device');
            Route::post('/devices/{device}/revoke', [App\Http\Controllers\Hr\LocationMonitoringController::class, 'revokeDevice'])->whereNumber('device');
        });

        Route::get('/approval-workflows', [App\Http\Controllers\Hr\ApprovalWorkflowController::class, 'index']);
        Route::put('/approval-workflows', [App\Http\Controllers\Hr\ApprovalWorkflowController::class, 'put']);

        Route::get('/approvals', [App\Http\Controllers\Hr\ApprovalRequestController::class, 'index']);
        Route::get('/approvals/{approvalRequest}', [App\Http\Controllers\Hr\ApprovalRequestController::class, 'show'])->whereNumber('approvalRequest');
        Route::post('/approvals/{approvalRequest}/approve', [App\Http\Controllers\Hr\ApprovalRequestController::class, 'approve'])->whereNumber('approvalRequest');
        Route::post('/approvals/{approvalRequest}/reject', [App\Http\Controllers\Hr\ApprovalRequestController::class, 'reject'])->whereNumber('approvalRequest');
        Route::post('/approvals/{approvalRequest}/cancel', [App\Http\Controllers\Hr\ApprovalRequestController::class, 'cancel'])->whereNumber('approvalRequest');

        Route::get('/audit-logs', [App\Http\Controllers\Hr\AuditLogController::class, 'index'])->middleware(['module:hr-audit']);
    });

    Route::prefix('logs')->middleware(['module:logs'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\LogController::class, 'index']);
        Route::get('/media-attachments', [App\Http\Controllers\General\MediaAttachmentAuditLogController::class, 'index']);
        Route::get('/models', [App\Http\Controllers\General\LogController::class, 'models']);
        Route::get('/types', [App\Http\Controllers\General\LogController::class, 'types']);
        Route::get('/statistics', [App\Http\Controllers\General\LogController::class, 'statistics']);
    });

    Route::prefix('statistics')->group(function () {
        Route::get('/admin_dashboard', [App\Http\Controllers\General\StatisticsController::class, 'admin_dashboard']);
        Route::get('/hr_dashboard', [App\Http\Controllers\General\StatisticsController::class, 'hr_dashboard']);
        Route::get('/case/{case}', [App\Http\Controllers\General\StatisticsController::class, 'case']);
    });

    Route::prefix('centers')->group(function () {
        Route::get('/', [App\Http\Controllers\General\CenterController::class, 'index']);
        Route::get('/items', [App\Http\Controllers\General\CenterController::class, 'filterItems']);
        Route::get('/{id}/show', [App\Http\Controllers\General\CenterController::class, 'show'])->middleware(['module:centers'])->name('center.show');
        Route::post('/put', [App\Http\Controllers\General\CenterController::class, 'put'])->middleware(['module:centers'])->name('center.put');
        Route::post('/{id}/activate', [App\Http\Controllers\General\CenterController::class, 'activate'])->middleware(['module:centers'])->name('center.activate');
        Route::delete('/{id}', [App\Http\Controllers\General\CenterController::class, 'delete'])->middleware(['module:centers']);
        Route::delete('/{id}/manager', [App\Http\Controllers\General\CenterController::class, 'deleteManager'])->middleware(['module:centers']);
        Route::delete('/{id}/logo', [App\Http\Controllers\General\CenterController::class, 'deleteLogo'])->middleware(['module:centers']);
        Route::patch('/{id}', [App\Http\Controllers\General\CenterController::class, 'restore'])->middleware(['module:centers']);
        Route::get('/{id}/calculate_payment', [App\Http\Controllers\General\CenterController::class, 'calculatePayment'])->middleware(['module:centers'])->name('center.calculate_payment');

        Route::get('/payments', [App\Http\Controllers\General\CenterController::class, 'payments'])->middleware(['module:centers']);
        Route::post('/payments/put', [App\Http\Controllers\General\CenterController::class, 'putPayment'])->middleware(['module:centers'])->name('center.put_payment');
        Route::post('/payments/{payment}/action', [App\Http\Controllers\General\CenterController::class, 'actionPayment'])->middleware(['module:centers']);
        Route::delete('/payments/{id}', [App\Http\Controllers\General\CenterController::class, 'deletePayment'])->middleware(['module:centers']);
        Route::patch('/payments/{id}', [App\Http\Controllers\General\CenterController::class, 'restorePayment'])->middleware(['module:centers']);

        Route::get('/users', [App\Http\Controllers\General\CenterController::class, 'users'])->middleware(['module:centers']);
        Route::post('/users/put', [App\Http\Controllers\General\CenterController::class, 'putUser'])->middleware(['module:centers'])->name('center.put_user');
        Route::delete('/users/{id}', [App\Http\Controllers\General\CenterController::class, 'deleteUser'])->middleware(['module:centers']);
        Route::patch('/users/{id}', [App\Http\Controllers\General\CenterController::class, 'restoreUser'])->middleware(['module:centers']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
