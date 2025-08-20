<?php

namespace App\Actions\Geospatial;

use App\Services\KadasterService;
use Exception;
use Illuminate\Database\Eloquent\Model;

class SyncGeometry
{
    public function __construct(
        private Model $model,
        private ?KadasterService $kadasterService = null,
        private string $geometryAttribute = 'geometry',
        private string $brkIdentificationAttribute = 'brk_identification',
        private string $brkUuidAttribute = 'brk_uuid'
    ) {
        $this->kadasterService = new KadasterService;
    }

    /**
     * Execute the sync geometry action.
     */
    public function execute(): void
    {
        $this->validateModelHasGeometry();
        $this->syncItemGeometry();
    }

    /**
     * Sync the geometry with data from the kadasterservice.
     */
    private function syncItemGeometry(): void
    {
        if ($identification = $this->model->{$this->brkIdentificationAttribute}) {
            // Sync the geometry for the item
            $response = $this->kadasterService->getGemeentegebiedByIdentification($identification);
            if ($response) {
                $this->model->{$this->geometryAttribute} = $response['geometry'];
                $this->model->{$this->brkUuidAttribute} = $response['id'] ?? null;
                $this->model->save();
            }
        }
    }

    /**
     * Validate that the model has a geometry attribute.
     *
     * @throws Exception
     */
    private function validateModelHasGeometry(): void
    {
        if (! $this->model->hasAttribute($this->geometryAttribute)) {
            throw new Exception("Model does not have a {$this->geometryAttribute} attribute for storing the geometric information");
        }

        if (! $this->model->hasAttribute($this->brkIdentificationAttribute)) {
            throw new Exception("Model does not have a {$this->brkIdentificationAttribute} attribute for identifying the BRK identification");
        }

        // todo validate geometry attribute type
    }
}
