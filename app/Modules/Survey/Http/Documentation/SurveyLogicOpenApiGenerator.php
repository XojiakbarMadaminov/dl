<?php

namespace App\Modules\Survey\Http\Documentation;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

class SurveyLogicOpenApiGenerator extends OpenApiGenerator
{
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if ($endpoint->httpMethods[0] !== 'PUT' || ! str_ends_with($endpoint->uri, '/surveys/{survey_id}/logic')) {
            return $pathItem;
        }

        $schema = &$pathItem['requestBody']['content']['application/json']['schema'];
        $value = &$schema['properties']['rules']['items']['properties']['conditions']['items']['properties']['value'];
        unset($value['type'], $value['nullable']);
        $value['oneOf'] = [['type' => 'integer'], ['type' => 'string', 'nullable' => true]];
        $schema['properties']['rules']['maxItems'] = 100;
        $schema['properties']['rules']['items']['properties']['conditions']['minItems'] = 1;
        $schema['properties']['rules']['items']['properties']['conditions']['maxItems'] = 20;

        return $pathItem;
    }
}
