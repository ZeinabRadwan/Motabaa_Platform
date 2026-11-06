<?php

namespace App\Support\Hr\Approvals;

use App\Models\Hr\ApprovalRequest;

/**
 * Implemented by business records that go through approval (leave requests, manual attendance, ...).
 * Called inside the approval transaction once the request is approved, rejected or cancelled.
 */
interface Approvable
{
    public function approvalResolved(ApprovalRequest $request): void;
}
