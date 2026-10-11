<?php

namespace App\Observers;

use App\Services\Report\ReportService;
use Illuminate\Database\Eloquent\Model;

class ReportCacheObserver
{
    public function saved(Model $model): void
    {
        $this->forget();
    }

    public function deleted(Model $model): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        app(ReportService::class)->forgetReports();
    }
}
