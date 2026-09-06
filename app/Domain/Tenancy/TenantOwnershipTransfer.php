<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Exceptions\OwnershipTransferRejected;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The ONLY supported way to move a record between tenants.
 *
 * It deliberately does not go through Eloquent: the write is issued on the base
 * query builder, so TenantBuilder's refusal of company_id stays absolute and
 * needs no bypass flag. The previous design used a process-global static flag,
 * which was both reachable from anywhere and unsound under any future
 * concurrent runtime.
 *
 * There is no endpoint and no UI. Reaching this from an arbitrary request would
 * require constructing a TransferAuthorization, which nothing binds from input.
 */
final class TenantOwnershipTransfer
{
    /**
     * @throws OwnershipTransferRejected
     */
    public function transfer(
        Model $model,
        Company|int $destination,
        TransferAuthorization $authorization,
    ): void {
        if (! in_array(BelongsToTenant::class, class_uses_recursive($model::class), true)) {
            throw OwnershipTransferRejected::notTenantOwned($model::class);
        }

        if (! $model->exists) {
            throw OwnershipTransferRejected::unsavedModel($model::class);
        }

        $destinationId = $destination instanceof Company ? $destination->getKey() : $destination;
        $sourceId = (int) $model->getAttribute('company_id');

        if ($sourceId === $destinationId) {
            throw OwnershipTransferRejected::sameCompany();
        }

        // Company is not tenant-scoped — it defines the boundary — so both ends
        // are verified to exist before anything is written.
        foreach ([$sourceId, $destinationId] as $companyId) {
            if (! Company::whereKey($companyId)->exists()) {
                throw OwnershipTransferRejected::unknownCompany($companyId);
            }
        }

        DB::transaction(function () use ($model, $sourceId, $destinationId, $authorization): void {
            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update(['company_id' => $destinationId]);

            // Audit trail until dedicated audit infrastructure exists; see
            // docs/architecture/TENANCY.md.
            Log::warning('tenant.ownership_transferred', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'from_company_id' => $sourceId,
                'to_company_id' => $destinationId,
                'performed_by' => $authorization->performedBy,
                'reason' => $authorization->reason,
            ]);
        });

        $model->setAttribute('company_id', $destinationId);
        $model->syncOriginalAttribute('company_id');
    }
}
