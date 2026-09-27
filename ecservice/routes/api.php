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

Route::get('/file/download/{token}', [App\Http\Controllers\General\FileController::class, 'download'])->name('file.download');

Route::prefix('questionnaires')->group(function () {
    Route::post('/{task}/fetch/questions', [App\Http\Controllers\General\QuestionnaireController::class, 'fetchQuestions']);
    Route::post('/{task}/add_answers', [App\Http\Controllers\General\QuestionnaireController::class, 'addAnswers']);
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
        Route::get('/select-items', [App\Http\Controllers\General\SCaseController::class, 'selectItems'])->middleware(['permission:'.App\Support\CaseListAccess::listPermissionMiddleware()]);
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
        Route::get('/show/{goal}', [App\Http\Controllers\General\GoalController::class, 'show'])->name('goal.show');
        Route::post('/transfer_goal/{goal}', [App\Http\Controllers\General\GoalController::class, 'transferGoal']);
        Route::get('/period_status', [App\Http\Controllers\General\GoalController::class, 'periodStatus']);
        Route::post('/period_action', [App\Http\Controllers\General\GoalController::class, 'periodAction']);
        Route::put('/put/{goal}', [App\Http\Controllers\General\GoalController::class, 'put']);
        Route::delete('/{id}', [App\Http\Controllers\General\GoalController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\GoalController::class, 'restore']);
        Route::get('/filter', [App\Http\Controllers\General\GoalController::class, 'filter']);
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
        Route::get('/task', [App\Http\Controllers\General\QuestionnaireController::class, 'questionnaireTask']);
        Route::post('/task', [App\Http\Controllers\General\QuestionnaireController::class, 'questionnaireTask']);
        Route::delete('/{id}', [App\Http\Controllers\General\QuestionnaireController::class, 'delete']);
        Route::patch('/{id}', [App\Http\Controllers\General\QuestionnaireController::class, 'restore']);
        Route::get('/{task}/show', [App\Http\Controllers\General\QuestionnaireController::class, 'show'])->name('questionnaire.show');
        Route::post('/fetch/questions_with_answers', [App\Http\Controllers\General\QuestionnaireController::class, 'fetchQuestionsWithAnswers'])->name('questionnaire.fetch_questions_with_answers');
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

    Route::prefix('logs')->middleware(['module:logs'])->group(function () {
        Route::get('/', [App\Http\Controllers\General\LogController::class, 'index']);
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
