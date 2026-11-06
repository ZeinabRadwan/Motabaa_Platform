<?php

namespace App\Console\Commands;

use App\Support\ParentDuplicateReport;
use Illuminate\Console\Command;

class DetectDuplicateParentAccountsCommand extends Command
{
    protected $signature = 'parent:detect-duplicates
                            {--output= : Write JSON report to this path (default: storage/app/parent-duplicates.json)}';

    protected $description = 'Generate a read-only report of possible duplicate parent-only accounts (no merges)';

    protected $aliases = ['parents:detect-duplicates'];

    public function handle(): int
    {
        $report = ParentDuplicateReport::build();

        $path = $this->option('output')
            ?: storage_path('app/parent-duplicates.json');

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $diagnostics = $report['email_scan_diagnostics'];

        $this->info('Parent-only accounts: '.$report['parent_account_count']);
        $this->info('Email duplicate clusters: '.count($report['email_clusters']));
        $this->info('Phone duplicate clusters: '.count($report['phone_clusters']));
        $this->info('  same email, different phone: '.count($report['email_match_phone_differs']));
        $this->info('  same phone, different email: '.count($report['phone_match_email_differs']));
        $this->info('  same email and phone: '.count($report['email_and_phone_match']));
        $this->info('Same full name, no shared email/phone (review only): '.count($report['same_name_no_shared_contact']));
        $this->info('Candidate groups: '.count($report['candidate_groups']));
        $this->info('Groups with conflicts (review): '.count($report['conflicts']));
        $this->info('Same-email clusters involving excluded accounts: '.count($diagnostics['same_email_clusters_with_excluded_accounts']));
        $this->info('Parent emails failing validation: '.count($diagnostics['parent_emails_failing_validation']));

        foreach ($diagnostics['parent_emails_failing_validation'] as $bad) {
            $this->line(sprintf(
                '  [invalid email] #%d name="%s" email="%s"%s',
                $bad['user_id'],
                $bad['name'],
                $bad['email'],
                $bad['invisible_characters'] ? ' hidden='.implode(',', $bad['invisible_characters']) : ''
            ));
        }

        foreach ($diagnostics['same_email_clusters_with_excluded_accounts'] as $cluster) {
            foreach ($cluster['users'] as $u) {
                $this->line(sprintf(
                    '  [same email, excluded] #%d name="%s" email="%s" reason=%s',
                    $u['id'],
                    $u['name'],
                    $u['email'],
                    $u['excluded_reason'] ?? 'included'
                ));
            }
        }

        foreach ($report['candidate_groups'] as $group) {
            $this->line(sprintf(
                '  [%s] group=%s match=%s primary=%d%s',
                $group['merge_status'],
                $group['group_key'],
                $group['match_type'],
                $group['primary_account_id'],
                $group['skip_reasons'] ? ' reasons='.implode(', ', $group['skip_reasons']) : ''
            ));
            if ($group['review_notes']) {
                $this->line('         notes: '.implode(', ', $group['review_notes']));
            }
            foreach ($group['users'] as $u) {
                $this->line(sprintf(
                    '         #%d name="%s" email=%s phone=%s students=%d',
                    $u['id'],
                    $u['name'],
                    $u['email'] ?? '-',
                    $u['phone'] ?? '-',
                    $u['linked_student_count']
                ));
            }
        }

        $this->line('Report: '.$path);
        $this->comment('This command does not modify data. Review the report before any merge.');

        return self::SUCCESS;
    }
}
