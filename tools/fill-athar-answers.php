<?php
require __DIR__ . '/../ecservice/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$input = $argv[1] ?? '';
$output = $argv[2] ?? $input;

$answers = [
    1 => 'No dedicated staging API yet. For full CRUD: (A) run the backend locally (Postman env "Athar Platform LOCAL", base_url http://127.0.0.1:8000/api) with your own DB, or (B) we will provide a dedicated test center on production with write access (not the read-only test account). Do not use production for destructive tests until we confirm the test center.',
    2 => 'Yes — mirror web permissions (CASL / Spatie), not managers only. V1 mobile admin is for center staff (manager, teacher, specialist, and any role with module access). Parents have a reduced UI on web; include only if in your scope. Platform admin (centers, global settings) is out of scope for the first five pages unless we add it later.',
    3 => 'Expected for now: ProfileController exists in code (GET/POST /account/profile, PUT /account/password) and the web client calls it, but routes are not registered on the deployed API yet — hence 404. Read-only profile in the app is fine until the next backend deploy when we register and ship these routes.',
    4 => 'We will provide: app icon 1024×1024 or SVG, privacy policy URL, and either store account access or we publish under our org and add you as developers — please confirm which model you prefer. Send bundle ID / package name early.',
    5 => 'Match the web: allow center switch; if status == 0 (not activated) or is_paid == false, show the same dashboard warnings and subscription/payment path — do not hard-block the whole app. The API does not enforce read-only when unpaid today.',
    6 => 'Yes. For center.package_id == 2, hide Independent skills, case attendance, study fees, operational plans, questionnaires, and staff attendance — same as web navigation (buildAppAndPages).',
    7 => 'Match web: API dates are Gregorian (Y-m-d). Show Hijri where the web does (e.g. birth date, attendance). UI: both — Gregorian pickers plus Hijri display for key fields; do not store Hijri-only in the API.',
    8 => 'Prefer in-app viewer (WebView/PDF) for UX. Technically: GET /goals?…&pdf=period_assessment returns data.url as /file/download?token=… — open as {API_BASE}/file/download?token=… (e.g. https://api.athrplatform.com/api/file/download?…). External browser is OK for v1 if faster.',
    9 => 'Not required for v1 (web does not block). Optional later: Android FLAG_SECURE on sensitive screens. Keeping screenshots on during testing is fine.',
    10 => 'V1: login + Sanctum token, center switch, permissions as web. Out of v1 unless we add a milestone: push notifications, offline mode, Face ID/fingerprint. Suggested minimums: iOS 15+, Android 8.0 (API 26)+ — confirm with your stack and store policies.',
    11 => 'You already have ecservice/docs/postman (LIVE + LOCAL) aligned with web flows. We can grant read-only backend source access (no production .env or keys). Use LOCAL Postman for safe writes; LIVE collection marks mutating requests.',
    12 => 'Today by design: GET /cases/{id}/show returns full SCaseResource for web case tabs; /goals/show/{id} may embed full case. Mobile: display only what you need and do not persist extra PII. Trimming responses is a backlog improvement, not a v1 blocker.',
    13 => 'Use the API base URL, not the frontend domain. PDF flow: GET /goals?…&pdf=period_assessment → data.url is /file/download?token=… → resolve as {VITE_APP_API_URL}/file/download?token=…. Opening on platform.* without /api shows HTML instead of the PDF — known; API domain is correct.',
    14 => 'POST /assessment-evaluations/list-weaks is read-only (prepares weak goals for end-of-assessment UI). Safe for test builds. POST …/move-weaks mutates data — treat as write.',
    15 => 'Web behavior is intentional: GET /goals/steps filters by goal_id only; teacher narrows case/goal pickers via sessionTeacherFilterId (admin + teacher send teacher_id; specialists must NOT send their user id as teacher_id). Align mobile with that helper — always sending teacher_id for specialists can break filters. Missing teacher_id on steps is not a bug.',
    16 => 'Not needed for mobile v1. Label exists in i18n only; no web implementation. Skip unless we add a PDF requirement later.',
    17 => 'Confirmed stable — same routes as web. Postman LIVE collection documents: goal steps CRUD/reorder, case files list/upload/delete, case photo delete, message attachment URL, password reset (Goals → Sessions, Cases → View, Login). Use LOCAL env for mutating tests.',
    18 => 'GET /fees?center_id=&case_id=&term_id= — row shape includes id, amount, case_id, case_name, term_id, term_name, services, services_names, notes, payments, paid_amount, status (Unpaid / Partially Paid / Paid). Hidden on package 2 / 403 without permission. We will share a center/case with fee data or you can seed LOCAL DB for UI work.',
];

$wb = IOFactory::load($input);
$ws = $wb->getActiveSheet();

for ($n = 1; $n <= 18; $n++) {
    $row = 4 + $n; // row 5 = question 1
    $ws->setCellValue('F' . $row, $answers[$n]);
    $ws->setCellValue('G' . $row, 'Answered 15 Oct 2026 — Motabaah / Athar platform (backend + web parity).');
}

$writer = IOFactory::createWriter($wb, 'Xlsx');
$writer->save($output);

echo "Saved: {$output}\n";
