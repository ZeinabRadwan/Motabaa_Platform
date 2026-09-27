<?php

use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Assessment;
use App\Models\SCase;
use App\Models\User;
use App\Models\Disability;
use App\Models\Service;
use App\Models\EvaluationMethod;
use App\Models\GoalEvaluation;
use App\Models\Goal;
use App\Models\AssessmentEvaluation;
use App\Models\FileSystem\BunnyFileSystem;
use App\Models\FileSystem\LaravelFileSystem;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Twilio\Rest\Client;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/download/{file_path}', function (Request $request) {

	$fileSystem = new LaravelFileSystem();
	return $fileSystem->download($request->file_path, false, $request->token, $request->expires);

})->where('file_path', '.*');

Route::get('/test', function () {
	abort_unless(app()->environment('local'), 404);

	$fileSystem = new BunnyFileSystem();//BunnyFileSystem();//LaravelFileSystem();

	//return $fileSystem->download("/refactor/animals/lion.jpg", force: true);

	//dd($fileSystem->remove("/refactor/tree.jpg"));

	//dd($fileSystem->size("/refactor/animals/lion.jpg"));

	//dd($fileSystem->mimeType("/refactor/animals/lion.jpg"));

	//dd($fileSystem->secureDownloadURL("/refactor/animals/lion.jpg"));

	//dd($fileSystem->list("refactor"));

	//dd($fileSystem->get("/refactor/tree.jpg"));

	//dd($fileSystem->upload("D:/xampp/htdocs/ecservice/storage/app/lion.jpg", "/refactor/animals"));
	
	dd($fileSystem->upload("D:/xampp/htdocs/ecservice/storage/app/tree.jpg", "/refactor"));

	dd("test");
});

Route::get('/statistics/admin_dashboard', [App\Http\Controllers\General\StatisticsController::class, 'admin_dashboard']);
Route::get('/statistics/case/{case}', [App\Http\Controllers\General\StatisticsController::class, 'case']);

Route::get('/whatsapp', function () {
	abort_unless(app()->environment('local'), 404);

	$sid = config('motabaa.whatsapp.sid');
	$token = config('motabaa.whatsapp.token');
	$twilio = new Client($sid, $token);

	$message = $twilio->messages
	->create("whatsapp:+201111589933", // to
		array(
			"from" => "whatsapp:+14155238886",
			"body" => "Your appointment is coming."
		)
	);

   print($message->sid);
});

Route::get('/', function () {

    return view('welcome');
});

Route::get('/fix', function () {
	abort_unless(app()->environment('local'), 404);

	/*d("fix Assessment(s)", "green");
	$assessments = Assessment::withTrashed()->get();
	foreach ($assessments as $assessment) {
		$assessment->save();
	}

	d("fix EvaluationMethod(s)", "green");
	DB::statement("TRUNCATE evaluation_methods_values;");
	$evaluationMethods = EvaluationMethod::all();
	foreach ($evaluationMethods as $evaluationMethod) {
		$evaluationMethod->save();
	}

	d("fix AssessmentEvaluation(s)", "green");
	$assessmentEvaluations = AssessmentEvaluation::all();
	foreach ($assessmentEvaluations as $assessmentEvaluation) {
		$assessmentEvaluation->save();
	}

	d("fix GoalEvaluation(s)", "green");
	$goalsEvaluations = GoalEvaluation::all();
	foreach ($goalsEvaluations as $goalEvaluation) {
		$goalEvaluation->save();
	}

	d("fix Goal(s)", "green");
	$goals = Goal::all();
	foreach ($goals as $goal) {
		$goal->save();
	}*/
	
	dd("done");
});

Route::get('/assessments', function () {
	abort_unless(app()->environment('local'), 404);
    
	$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
	$excel = $reader->load(storage_path('app') . "/files/communication.xlsx");
	$sheetCount = $excel->getSheetCount();

	for ($i = 0; $i < $sheetCount; $i++) {		
	    
	    $sheet = $excel->getSheet($i);
	    $root = Assessment::where('title', $sheet->getTitle())
	    ->whereNull('parent_id')
	    ->where('type', 0)
	    ->first();

	    if(!$root) {
	    	
	    	$root = new Assessment();
        	$root->title = $sheet->getTitle();
        	$root->title_local = $sheet->getTitle();
        	$root->parent_id = null;
        	$root->type = 0;
        	$root->save();
	    }
	    
	    $rows = $sheet->toArray(null, true, true, true);
	    $goalKey = null;

	    foreach ($rows as $values) {

	    	if(empty($values)) continue;
	    	$keys = array_keys($values);
	    	
	    	if(!$goalKey) {

	    		$goalKeyIndex = count($keys) - 1;
	    		while($goalKeyIndex>=0) {
	    		
		    		$goalKey = $keys[$goalKeyIndex];
		    		if(!empty($values[$goalKey])) break;
		    		$goalKeyIndex--;		    		
		    	}

		    	if($goalKeyIndex<0) {
	    			dd("Error in header", $values);
	    		}

		    	continue;
		    }

	    	$parent = $root;

	    	foreach ($values as $key => $value) {

	    		d("$key => $value");

	    		if(empty($value)) continue;

	    		$assessment = Assessment::where('title', $value)
	    		->when(
		            $parent,
		            fn ($q) => $q->where('parent_id', $parent->id)
		        )->first();

		        if(!$assessment) {

		        	$assessment = new Assessment();
		        }

	        	$assessment->title = $value;
	        	$assessment->title_local = $value;
	        	$assessment->parent_id = $parent->id;
	        	$assessment->type = ($key!=$goalKey)?1:2;
	        	$assessment->save();

		        if($key==$goalKey) break;
		        $parent = $assessment;
		    }
	    }
	}

	dd("done");
});

Route::get('/cases', function () {
	abort_unless(app()->environment('local'), 404);

	$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
	$excel = $reader->load(storage_path('app') . "/files/cases.xlsx");
	$sheetCount = $excel->getSheetCount();

	$genders = [];
	$genders['ذكر'] = 1; 
	$genders['انثى'] = 2; 

	$periods = [];
	$periods['فترة صباحية'] = 1; 
	$periods['فترة مسائية'] = 0; 

	$nationalities = [];
	$nationalities['سعودي'] = 'SA';

	$columns = [
		"code" => "A",
		"name" => "B",
		"registratison" => "C",
		"beneficiary_number" => "D",
		"period" => "E",
		"gender" => "F",
		"age" => "G",
		"disability" => "H",
		"service1" => "I",
		"service2" => "J",
		"service3" => "K",
		"service4" => "L",
		"service5" => "M",
		"teacher" => "N",
		"natural_specialist" => "O",
		"occupational_specialist" => "P",
		"speech_specialist" => "Q",
		"psychologist" => "R",
		"id_or_residence_number" => "S",
		"nationality" => "T",
		"birthdate" => "U",
		"parent" => "V",
		"urgent_phone" => "W",
		"mobile" => "X",
		"blood" => "Y",
		"address_apartment_no" => "Z",
		"address_building_no" => "AA",
		"address_street" => "AB",
		"address_area" => "AC",
		"address_city" => "AD",
		"address_postal_code" => "AE",
		"address_alt_mobile" => "AF"
	];

    $nEmpty = 0;
    $nEmptyName = 0;

	for ($i = 0; $i < $sheetCount; $i++) {		
	    
	    $sheet = $excel->getSheet($i);

	    $rows = $sheet->toArray(null, true, true, true);

	    $headers = null;

	    foreach ($rows as $values) {

	    	if(empty($values)) {
	    		$nEmpty++;
	    		continue;
	    	}
	    	if(empty($headers)) { 
	    		$headers = (object)array_flip($values); 
	    		continue; 
	    	}
	    	if(empty($values[$headers->name])) {
	    		$nEmptyName++;
	    		continue;
	    	}

	    	$case = SCase::where('id_or_residence_number', $values[$headers->id_or_residence_number])->first();

	        if(!$case) $case = new SCase();

	        if(!array_key_exists($values[$headers->period], $periods)) {
	        	em("Missing period value:".$values[$headers->period]);
	        	continue;
	        }

	        if(!array_key_exists($values[$headers->nationality], $nationalities)) {
	        	em("Missing nationality value:".$values[$headers->nationality]);
	        	continue;
	        }

	        if(!array_key_exists($values[$headers->gender], $genders)) {
	        	em("Missing gender value:".$values[$headers->gender]);
	        	continue;
	        }

	        $birthdate = $values[$headers->birthdate];
	        if($birthdate) {
	        	$date = explode("/", $birthdate);
	        	if(count($date)==3 && 
	        		$date[0]>=1 && $date[0]<=12 && 
	        		$date[1]>=1 && $date[1]<=30) {

	        		if($date[2]>1950) {

	        			$birthdate = Carbon::createFromDate($date[2], $date[0], $date[1]);

	        		} else if($date[2]>1350) {

	        			$date = new HijriDate($date[2], $date[0], $date[1]);
	        			$birthdate = $date->getGregorianDate();
	        			
	        		} else {

	        			$birthdate = null;
	        		}	        		
	        	}
        	}

	        $disability = Disability::where('name_ar', $values[$headers->disability])->first();
	        $services = [];
	        $service = Service::where('name_ar', $values[$headers->service1])->first();
	        if($service) $services[] = $service;
	        $service = Service::where('name_ar', $values[$headers->service2])->first();
	        if($service) $services[] = $service;
	        $service = Service::where('name_ar', $values[$headers->service3])->first();
	        if($service) $services[] = $service;
	        $service = Service::where('name_ar', $values[$headers->service4])->first();
	        if($service) $services[] = $service;
	        $service = Service::where('name_ar', $values[$headers->service5])->first();
	        if($service) $services[] = $service;

	    	$case->name = $values[$headers->name];
	    	$case->beneficiary_number = $values[$headers->beneficiary_number];
	    	$case->period = $periods[$values[$headers->period]];
	    	$case->nationality = $nationalities[$values[$headers->nationality]];
	    	$case->gender = $genders[$values[$headers->gender]];
	    	$case->id_or_residence_number = $values[$headers->id_or_residence_number];
	    	$case->birthdate = $birthdate;

	    	$case->phone = $values[$headers->phone];
	    	$case->emergency_contact = $values[$headers->emergency_contact];

	    	$case->blood_type = $values[$headers->blood_type];

	    	$case->address_unit = $values[$headers->address_unit];
	    	$case->address_building = $values[$headers->address_building];
	    	$case->address_street = $values[$headers->address_street];
	    	$case->address_area = $values[$headers->address_area];
	    	$case->address_city = $values[$headers->address_city];
	    	$case->address_zipcode = $values[$headers->address_zipcode];
	    	$case->address_number = $values[$headers->address_number];

	    	$case->save();

	    	$emergencyContact = $values[$headers->emergency_contact];
	    	$phone = $values[$headers->phone];
	    	$parent = null;
	    	if($emergencyContact) $parent = User::where('phone', $emergencyContact)->first();
	    	if($phone && !$parent) $parent = User::where('phone', $phone)->first();	    	

	    	if($parent) DB::statement("INSERT INTO scase_user(scase_id, user_id, relationship_type) VALUES($case->id, $parent->id, 0)");

	    	if($disability && !$case->disabilities()->where('disability_id', $disability->id)->exists()) DB::statement("INSERT INTO scase_disability(scase_id, disability_id) VALUES($case->id, $disability->id)");
	    	foreach ($services as $service) {
	    		if(!$case->services()->where('service_id', $service->id)->exists()) DB::statement("INSERT INTO scase_service(scase_id, service_id) VALUES($case->id, $service->id)");
	    	}
	    }	    
	}

	dd("done", $nEmpty, $nEmptyName);
});

Route::get('/parents', function () {
	abort_unless(app()->environment('local'), 404);

	$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
	$excel = $reader->load(storage_path('app') . "/files/parents.xlsx");
	$sheetCount = $excel->getSheetCount();	

	$nationalities = [];
	$nationalities['سعودي'] = 'SA';

	for ($i = 0; $i < $sheetCount; $i++) {		
	    
	    $sheet = $excel->getSheet($i);

	    $rows = $sheet->toArray(null, true, true, true);

	    $headers = null;

	    foreach ($rows as $values) {

	    	if(empty($values)) continue;
	    	if(empty($headers)) { 
	    		$headers = (object)array_flip($values); 
	    		continue; 
	    	}
	    	if(empty($values[$headers->name])) continue;
	    	if(empty($values[$headers->email])) continue;

	    	$user = User::where('id_or_residence_number', $values[$headers->id_or_residence_number])->first();

	        if(!$user) {
	        	
	        	$user = new User();
	        	$user->password = bcrypt('P@ssw0rd');
	        }

	        if(!array_key_exists($values[$headers->nationality], $nationalities)) {
	        	em("Missing nationality value:".$values[$headers->nationality]);	        
	        	continue;
	        }

	        if(empty($values[$headers->email])) {

	        	dd($values);
	        }
	        
	    	$user->name = $values[$headers->name];
	    	$user->email = $values[$headers->email];	    	
	    	$user->nationality = $nationalities[$values[$headers->nationality]];
	    	$user->id_or_residence_number = $values[$headers->id_or_residence_number];

	    	$user->phone = $values[$headers->phone];

	    	$user->address_unit = $values[$headers->address_unit];
	    	$user->address_building = $values[$headers->address_building];
	    	$user->address_street = $values[$headers->address_street];
	    	$user->address_area = $values[$headers->address_area];
	    	$user->address_city = $values[$headers->address_city];
	    	$user->address_zipcode = $values[$headers->address_zipcode];
	    	$user->address_number = $values[$headers->address_number];

	    	$user->save();

	    	$user->syncRoles([2]);
	    }

	    dd($rows);
	}

	dd("done");
});

Route::get('/employees', function () {
	abort_unless(app()->environment('local'), 404);

	$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
	$excel = $reader->load(storage_path('app') . "/files/employees.xlsx");
	$sheetCount = $excel->getSheetCount();	

	$nationalities = [];
	$nationalities['سعودية'] = 'SA';
	$nationalities['مصرية'] = 'EG';
	$nationalities['سودانية'] = 'SD';

	for ($i = 0; $i < $sheetCount; $i++) {		
	    
	    $sheet = $excel->getSheet($i);

	    $rows = $sheet->toArray(null, true, true, true);

	    $headers = null;

	    foreach ($rows as $values) {

	    	if(empty($values)) continue;
	    	if(empty($headers)) { 
	    		$headers = (object)array_flip($values); 
	    		continue; 
	    	}
	    	if(empty($values[$headers->name])) continue;
	    	if(empty($values[$headers->email])) continue;

	    	$user = User::where('id_or_residence_number', $values[$headers->id_or_residence_number])->first();

	        if(!$user) {
	        	
	        	$user = new User();
	        	$user->password = bcrypt('P@ssw0rd');
	        }

	        if(!array_key_exists($values[$headers->nationality], $nationalities)) {
	        	dd("Missing nationality value:", $values, $values[$headers->nationality]);
	        }

	        $role = Role::where('name', $values[$headers->role])->first();
	    	if(!$role) {
	    		dd("Invalid role", $values);
	    	}

	        if(empty($values[$headers->email])) {

	        	dd("Empty email", $values);
	        }
	        
	    	$user->name = $values[$headers->name];
	    	$user->email = $values[$headers->email];	    	
	    	$user->nationality = $nationalities[$values[$headers->nationality]];
	    	$user->id_or_residence_number = $values[$headers->id_or_residence_number];

	    	$user->phone = $values[$headers->phone];

	    	$user->address_unit = $values[$headers->address_unit];
	    	$user->address_building = $values[$headers->address_building];
	    	$user->address_street = $values[$headers->address_street];
	    	$user->address_area = $values[$headers->address_area];
	    	$user->address_city = $values[$headers->address_city];
	    	$user->address_zipcode = $values[$headers->address_zipcode];
	    	$user->address_number = $values[$headers->address_number];

	    	$user->save();	    	

	    	$user->syncRoles([$role->id]);
	    }

	    dd($rows);
	}

	dd("done");
});