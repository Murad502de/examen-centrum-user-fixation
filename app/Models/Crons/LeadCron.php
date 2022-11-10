<?php

namespace App\Models\Crons;

use App\Models\Services\amoCRM;
use App\Services\amoAPI\amoAPIHub;
use App\Traits\Middleware\Services\AmoCRM\AmoTokenExpirationControlTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use App\Services\amoAPI\Entities\Lead as AmoLead;

// use Illuminate\Support\Facades\Log;

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
                $stageName = self::getStageNameById(
                    (int) json_decode($lead->data)->status_id,
                    (int) json_decode($lead->data)->pipeline_id
                );

                // Log::info(__METHOD__, ['modified_user_id: ' . json_decode($lead->data)->modified_user_id]); //DELETE
                // Log::info(__METHOD__, [$stageName . " : " . config('services.amoCRM.stage_name_signed_for_trial')]); //DELETE

                if ($stageName === config('services.amoCRM.stage_name_signed_for_trial')) {
                    // Log::info(__METHOD__, ['das Lead muss aktualisiert werden']); //DELETE

                    $user      = self::$amoAPIHub->fetchUser((int) json_decode($lead->data)->modified_user_id);
                    $userName  = $user['body']['name'];
                    $userGroup = count($user['body']['_embedded']['groups']) ? $user['body']['_embedded']['groups'][0]['name'] : null;

                    // Log::info(__METHOD__, ['user: ', $userName . ' ' . $userGroup]); //DELETE
                    // Log::info(__METHOD__, [config('services.amoCRM.field_id_fullname')]); //DELETE
                    // Log::info(__METHOD__, [config('services.amoCRM.field_id_department')]); //DELETE

                    self::$amoAPIHub->updateLead([[
                        "id"                   => (int) $lead->lead_id,
                        'custom_fields_values' => [
                            [
                                'field_id' => (int) config('services.amoCRM.field_id_fullname'),
                                'values'   => [
                                    [
                                        'value' => $userName,
                                    ],
                                ],
                            ],
                            [
                                'field_id' => (int) config('services.amoCRM.field_id_department'),
                                'values'   => [
                                    [
                                        'value' => $userGroup,
                                    ],
                                ],
                            ],
                        ],
                    ]]);
                }

                // $lead->delete(); //TODO
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
                    'type' => $customField['type'],
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
