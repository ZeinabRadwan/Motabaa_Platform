<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @include('pdf.questionnaires._report_styles')
</head>
<body>
    <h2>{{ $data['title'] ?? '' }}</h2>
    <p class="muted">{{ __('tr.questionnaires.analytics_report_generated_at') }}: {{ $data['generated_at'] ?? '' }}</p>

    <h3>{{ __('tr.questionnaires.analytics_report_filters') }}</h3>
    <table class="summary-table">
        <tr>
            <th>{{ __('tr.questionnaires.filter_term') }}</th>
            <td>{{ data_get($data, 'filter_labels.term') ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.filter_date_range') }}</th>
            <td>{{ data_get($data, 'filter_labels.period') ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.filter_completion') }}</th>
            <td>{{ data_get($data, 'filter_labels.completion') ?: '—' }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.filter_respondent') }}</th>
            <td>{{ data_get($data, 'filter_labels.respondent') ?: '—' }}</td>
        </tr>
    </table>
    <!--mpdf-chunk-->

    <h3>{{ __('tr.questionnaires.analytics_report_summary') }}</h3>
    <table class="summary-table">
        <tr>
            <th>{{ __('tr.questionnaires.kpi_targeted_users') }}</th>
            <td>{{ data_get($data, 'kpis.targeted_users', 0) }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.kpi_started_incomplete') }}</th>
            <td>{{ data_get($data, 'kpis.started_incomplete', 0) }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.kpi_completed') }}</th>
            <td>{{ data_get($data, 'kpis.completed_submissions', 0) }}</td>
        </tr>
        <tr>
            <th>{{ __('tr.questionnaires.kpi_response_rate') }}</th>
            <td>{{ data_get($data, 'kpis.response_rate_percent', 0) }}%</td>
        </tr>
    </table>
    <!--mpdf-chunk-->

    <h3>{{ __('tr.questionnaires.analytics_report_questions') }}</h3>
    @forelse(($data['questions'] ?? []) as $question)
        <div class="question-block">
            <h4>
                {{ __('tr.questionnaires.question_number') }} {{ $question['question_number'] ?? '' }}:
                {{ $question['title'] ?? '' }}
            </h4>
            <p class="muted">
                {{ __('tr.questionnaires.question_type') }}:
                @if(($question['type'] ?? 0) == 1)
                    {{ __('tr.questionnaires.question_type_text') }}
                @else
                    {{ __('tr.questionnaires.question_type_choice') }}
                @endif
                —
                {{ __('tr.questionnaires.answered') }}: {{ $question['answered'] ?? 0 }}
                /
                {{ __('tr.questionnaires.skipped') }}: {{ $question['skipped'] ?? 0 }}
            </p>

            @if(!empty($question['option_stats']))
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('tr.questionnaires.option_label') }}</th>
                            <th>{{ __('tr.questionnaires.option_count') }}</th>
                            <th>{{ __('tr.questionnaires.option_percent') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($question['option_stats'] as $row)
                            <tr>
                                <td>{{ $row['answer'] ?? '' }}</td>
                                <td>{{ $row['count'] ?? 0 }}</td>
                                <td>{{ $row['percent'] ?? 0 }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif(!empty($question['legacy_choice_stats']))
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('tr.questionnaires.option_label') }}</th>
                            <th>{{ __('tr.questionnaires.option_count') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($question['legacy_choice_stats'] as $row)
                            <tr>
                                <td>{{ $row['answer'] ?? '' }}</td>
                                <td>{{ $row['count'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif(!empty($question['answers']))
                @php($answerChunks = array_chunk($question['answers'], 15))
                @foreach($answerChunks as $chunkIndex => $answerChunk)
                    @if($chunkIndex > 0)
                        <!--mpdf-chunk-->
                    @endif
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('tr.questionnaires.export_account_name') }}</th>
                                <th>{{ __('tr.questionnaires.text_answer') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($answerChunk as $row)
                                <tr>
                                    <td>{{ $row['id'] ?? '' }}</td>
                                    <td>{{ $row['account_name'] ?? __('tr.questionnaires.metadata_unavailable') }}</td>
                                    <td>
                                        {{ $row['answer'] ?? '' }}
                                        @if(!empty($row['answer_truncated']))
                                            <span class="muted"> ({{ __('tr.questionnaires.pdf_answer_truncated') }})</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
                @if(!empty($question['answers_truncated']))
                    <p class="muted">
                        {{ __('tr.questionnaires.pdf_answers_truncated', ['count' => $question['answers_truncated']]) }}
                    </p>
                @endif
            @else
                <p class="muted">{{ __('tr.questionnaires.pdf_no_question_responses') }}</p>
            @endif
        </div>
        <!--mpdf-chunk-->
    @empty
        <p class="muted">{{ __('tr.questionnaires.pdf_no_questions') }}</p>
    @endforelse
</body>
</html>
