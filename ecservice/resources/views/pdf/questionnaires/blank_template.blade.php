<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @include('pdf.questionnaires._report_styles')
    <style>
        .template-intro { margin-bottom: 16px; }
        .section-heading {
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ccc;
        }
        .question-card {
            page-break-inside: avoid;
            margin-bottom: 20px;
            padding-bottom: 8px;
        }
        .option-row {
            margin: 6px 0;
            padding-right: 8px;
        }
        .option-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #333;
            margin-left: 8px;
            vertical-align: middle;
        }
        .answer-lines {
            margin-top: 10px;
            border-bottom: 1px dotted #999;
            height: 22px;
        }
        .required-mark { color: #c00; }
    </style>
</head>
<body>
    <h2>{{ $data['title'] ?? '' }}</h2>
    <p class="muted template-intro">{{ __('tr.questionnaires.template_export_generated_at') }}: {{ $data['generated_at'] ?? '' }}</p>

    @forelse(($data['sections'] ?? []) as $section)
        @if(!empty($section['title']))
            <h3 class="section-heading">{{ $section['title'] }}</h3>
        @endif

        @foreach($section['questions'] ?? [] as $question)
            <div class="question-card">
                <h4>
                    {{ __('tr.questionnaires.question_number') }} {{ $question['number'] ?? '' }}:
                    {{ $question['title'] ?? '' }}
                    @if(!empty($question['required']))
                        <span class="required-mark">*</span>
                    @endif
                </h4>
                <p class="muted">
                    {{ __('tr.questionnaires.question_type') }}:
                    @if(($question['type'] ?? 0) == 1)
                        {{ __('tr.questionnaires.question_type_text') }}
                    @else
                        {{ __('tr.questionnaires.question_type_choice') }}
                    @endif
                </p>

                @if(($question['type'] ?? 0) == 2 && !empty($question['options']))
                    @foreach($question['options'] as $option)
                        <div class="option-row">
                            <span class="option-box"></span>
                            {{ $option }}
                        </div>
                    @endforeach
                @elseif(($question['type'] ?? 0) == 1)
                    <div class="answer-lines"></div>
                    <div class="answer-lines"></div>
                    <div class="answer-lines"></div>
                @endif
            </div>
            <!--mpdf-chunk-->
        @endforeach
    @empty
        <p class="muted">{{ __('tr.questionnaires.pdf_no_questions') }}</p>
    @endforelse
</body>
</html>
