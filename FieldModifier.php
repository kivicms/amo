<?php

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\EntitiesServices\Leads;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;

class FieldModifier
{
    protected const FIELD_ONE_ID = 86677;
    protected const FIELD_TWO_ID = 86679;
    protected AmoCRMApiClient $apiClient;
    protected Leads $leadService;
    protected $leadIds = [];

    public function __construct(AmoCRMApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->leadService = $apiClient->leads();
        if (isset($_POST['leads']['add'])) {
            $this->leadIds = array_column($_POST['leads']['add'], 'id');
        }
    }

    public function execute()
    {
        foreach ($this->leadIds as $id) {
            $lead = $this->leadService->getOne($id);
            $fieldValuesCollection = $lead->getCustomFieldsValues();
            $firstField = $fieldValuesCollection->getBy('fieldId', self::FIELD_ONE_ID);

            $twoField = new NumericCustomFieldValuesModel();
            $twoField->setFieldId(self::FIELD_TWO_ID);

            $firstValue = $firstField->getValues();

            $twoField->setValues(
                (new NumericCustomFieldValueCollection())
                    ->add(
                        (new NumericCustomFieldValueModel())->setValue(
                            $firstValue->first()->value * 2
                        )
                    )
            );
            $fieldValuesCollection->add($twoField);
            $lead->setCustomFieldsValues($fieldValuesCollection);
            $this->leadService->updateOne($lead);
        }
    }
}
