<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPhoneNumber;
use App\Support\ParentIdentity;
use App\Support\ParentPhoneNumberRules;
use App\Support\ParentPhoneNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies each parent-only account's users.phone into user_phone_numbers as the verified primary
 * (verification_method = legacy_primary: it is already the account's login identifier). users.phone is
 * never modified. Placeholders of merged accounts and non-Saudi/invalid numbers are skipped and reported.
 */
class BackfillParentPhoneNumbersCommand extends Command
{
    protected $signature = 'parent:backfill-phone-numbers
                            {--apply : Write rows (default is dry-run only)}
                            {--force : Skip confirmation prompt when using --apply}
                            {--output= : Write JSON report path (default: storage/app/parent-phone-backfill.json)}';

    protected $description = 'Materialize each parent account\'s existing phone as its verified primary number (dry-run by default)';

    public function handle(): int
    {
        if (! User::hasPhoneNumbersTable()) {
            $this->error('Table user_phone_numbers does not exist. Run the migration first.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $path = $this->option('output') ?: storage_path('app/parent-phone-backfill.json');

        if ($apply) {
            $this->warn('APPLY MODE: rows will be inserted into user_phone_numbers. users.phone is not changed.');
            if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }
        }

        $parents = ParentIdentity::scopeParentOnlyAccounts(User::withTrashed())
            ->whereNull('merged_into_user_id')
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'deleted_at']);

        $rowsByUser = UserPhoneNumber::query()
            ->whereIn('user_id', $parents->pluck('id'))
            ->get(['user_id', 'phone', 'is_primary'])
            ->groupBy('user_id');

        $report = ['generated_at' => now()->toIso8601String(), 'mode' => $apply ? 'apply' : 'dry_run', 'counts' => [], 'items' => []];
        $counts = [];

        foreach ($parents as $parent) {
            $existing = ($rowsByUser[$parent->id] ?? collect())
                ->map(fn ($r) => ['phone' => $r->phone, 'is_primary' => (bool) $r->is_primary])
                ->values()
                ->all();
            $normalized = ParentPhoneNumberRules::normalize($parent->phone);
            $decision = ParentPhoneNumberRules::backfillDecision(
                $parent->phone,
                $existing,
                $normalized !== null && ParentPhoneNumberService::verifiedElsewhere($normalized, (int) $parent->id)
            );

            $written = false;
            if ($apply && in_array($decision, [ParentPhoneNumberRules::BACKFILL_CREATE, ParentPhoneNumberRules::BACKFILL_PROMOTE], true)) {
                $written = DB::transaction(function () use ($parent) {
                    $locked = User::withTrashed()->lockForUpdate()->find($parent->id);

                    return $locked !== null && ParentPhoneNumberService::ensurePrimaryRow($locked) !== null;
                });
            }

            $counts[$decision] = ($counts[$decision] ?? 0) + 1;
            if ($decision !== ParentPhoneNumberRules::BACKFILL_SKIP_HAS_PRIMARY) {
                $report['items'][] = [
                    'user_id' => (int) $parent->id,
                    'name' => $parent->name,
                    'phone' => $parent->phone,
                    'normalized_phone' => $normalized,
                    'soft_deleted' => $parent->deleted_at !== null,
                    'decision' => $decision,
                    'written' => $written,
                ];
            }
        }

        $report['counts'] = $counts;
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Parent-only accounts scanned: '.$parents->count());
        foreach ($counts as $decision => $count) {
            $this->line(sprintf('  %-28s %d', $decision, $count));
        }
        foreach ($report['items'] as $item) {
            if (! in_array($item['decision'], [ParentPhoneNumberRules::BACKFILL_CREATE, ParentPhoneNumberRules::BACKFILL_PROMOTE], true)) {
                $this->line(sprintf('  [skip] #%d %s phone=%s reason=%s', $item['user_id'], $item['name'], $item['phone'] ?? '-', $item['decision']));
            }
        }
        if (! $apply) {
            $this->comment('Dry run only — no database changes. Re-run with --apply after review.');
        }
        $this->line('Report: '.$path);

        return self::SUCCESS;
    }
}
