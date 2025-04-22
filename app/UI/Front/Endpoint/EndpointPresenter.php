<?php
namespace App\UI\Front\Endpoint;

use Nette;
use App\Model\RepairFacade;

class EndpointPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private RepairFacade $repairFacade,
    ) {}

    public function actionManufacturers($typeId): void
    {
        $manufacturers = $this->repairFacade->getManufacturersByType($typeId);
        $this->sendJson($manufacturers);
    }

    public function actionModels($manufacturerId): void
    {
        $models = $this->repairFacade->getModelsByManufacturer($manufacturerId);
        $this->sendJson($models);
    }

    public function actionFaults($faultId): void
    {
        $faults = $this->repairFacade->getFaults($faultId);
        $this->sendJson($faults);
    }

    public function actionGetRepairPrice($modelId, $faultId): void
    {
        $modelId = (int) $modelId;
        $faultId = (int) $faultId;
        $price = $this->repairFacade->getRepairPrice($modelId, $faultId);
        $this->sendJson(['price' => $price ?? 'N/A']);
        $this->terminate();
    }
}