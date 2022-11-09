<?php

namespace App\Models\Crons;

use App\Models\Services\amoCRM;
use App\Services\amoAPI\amoAPIHub;
use App\Traits\Middleware\Services\AmoCRM\AmoTokenExpirationControlTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Facades\Log;

// use App\Services\amoAPI\Entities\Lead as AmoLead;

class LeadCron extends Model
{
    use HasFactory;
    use AmoTokenExpirationControlTrait;

    protected $fillable = [
        'lead_id',
        'last_modified',
        'data',
    ];
    protected $hidden = [
        'id',
    ];

    private const PARSE_COUNT = 20;
    private static $amoAPIHub;

    public static function createLead(string $leadId, int $lastModified, array $data): void
    {
        self::create([
            'lead_id'       => $leadId,
            'last_modified' => (int) $lastModified,
            'data'          => json_encode($data),
        ]);
    }
    public static function getLeadByAmoId(string $leadId): ?LeadCron
    {
        return self::all()->where('lead_id', $leadId)->first();
    }
    public static function updateLead(string $leadId, int $lastModified, array $data): void
    {
        self::where('lead_id', $leadId)->update([
            'last_modified' => (int) $lastModified,
            'data'          => json_encode($data),
        ]);
    }
    public static function parseRecentWebhooks()
    {
        // Log::info(__METHOD__, ['Scheduler::[LeadCron][parseRecentWebhooks]']); //DELETE

        if (self::amoTokenExpirationControl()) {
            self::$amoAPIHub = new amoAPIHub(amoCRM::getAuthData());
            $leads           = self::getLeads();

            foreach ($leads as $lead) {
                $fieldId = self::getFieldIdByName(
                    self::getStageNameById(
                        (int) json_decode($lead->data)->status_id,
                        (int) json_decode($lead->data)->pipeline_id
                    )
                );

                if ($fieldId) {
                    // Log::info(__METHOD__, ['geben datum im feld-stufe ein']); //DELETE

                    self::$amoAPIHub->updateLead([
                        [
                            "id"                   => (int) $lead->lead_id,
                            'custom_fields_values' => [
                                [
                                    'field_id' => $fieldId,
                                    'values'   => [
                                        [
                                            'value' => time(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);
                }

                $lead->delete();
            }
        }
    }

    /* PROCEDURES */

    /* FUNCTIONS */
    public static function getLeads()
    {
        return self::orderBy('id', 'asc')
            ->take(self::PARSE_COUNT)
            ->get();
    }
    public static function getStageNameById(int $stageId, int $pipelineId): string
    {
        $stageName = '';
        $stage     = self::$amoAPIHub->getLeadStageById($stageId, $pipelineId);

        if ($stage) {
            $stageName = str_replace(' ', '', trim(mb_strtolower($stage['name'])));
        }

        return $stageName;
    }
    public static function getFieldIdByName(string $str): ?int
    {
        if (!$str) {
            return null;
        }

        $fieldsNode = self::$amoAPIHub->list('customFields');
        $fields     = [];
        $fieldId    = null;

        foreach ($fieldsNode as $fieldNode) {
            $customFields = $fieldNode['_embedded']['custom_fields'];

            foreach ($customFields as $customField) {
                $fields[] = [
                    'id'   => $customField['id'],
                    'type'   => $customField['type'],
                    'name' => str_replace(' ', '', trim(mb_strtolower($customField['name']))),
                ];
            }
        }

        foreach ($fields as $field) {
            if (
                $field['type'] === 'date_time' &&
                $field['name'] === $str
            ) {
                $fieldId = (int) $field['id'];

                // Log::info(__METHOD__, ['id: ' . $fieldId]); //DELETE
                // Log::info(__METHOD__, ['name: ' . $field['name']]); //DELETE
                // Log::info(__METHOD__, ['str: ' . $str]); //DELETE
            }
        }

        return $fieldId; //DELETE
    }
}
