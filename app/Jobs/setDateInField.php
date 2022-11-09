<?php

namespace App\Jobs;

use App\Jobs\Middleware\AmoTokenExpirationControl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Crons\LeadCron;

class setDateInField implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private $lead;
    private $fieldId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(LeadCron $lead, int $fieldId)
    {
        $this->lead  = $lead;
        $this->fieldId = $fieldId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info(__METHOD__, ['lead: ' . $this->lead]); //DELETE
        Log::info(__METHOD__, ['fieldId: ' . $this->fieldId]); //DELETE

        Log::info(__METHOD__, ["DEL::WEBHOOK : " . $this->lead->lead_id]); //DELETE
    }

    /**
     * Get the intermediary through which the job should go.
     *
     * @return array
     */
    public function middleware()
    {
        return [new AmoTokenExpirationControl];
    }
}
