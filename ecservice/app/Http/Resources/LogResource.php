<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $model = $this->model();
        $isDescriptionJson = isJson($this->description);
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy ? $this->createdBy->name : '',
            'type' => $this->type,
            'is_description_json' => $isDescriptionJson,
            'description' => $isDescriptionJson ? json_decode($this->description, true) : $this->description,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'model_type_name' =>  $model['type_name'] ? $model['type_name'] : $this->model_type,
            'model_id_name' =>  $model['id_name'] ? $model['id_name'] : $this->model_id,
            'url' => $this->url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
