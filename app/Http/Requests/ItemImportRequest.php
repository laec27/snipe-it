<?php

namespace App\Http\Requests;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ItemImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'import-type' => 'required',
        ];
    }

    public function import(Import $import)
    {
        Log::debug('in import');
        ini_set('max_execution_time', env('IMPORT_TIME_LIMIT', 600)); //600 seconds = 10 minutes
        ini_set('memory_limit', env('IMPORT_MEMORY_LIMIT', '500M'));
        
        $filename = config('app.private_uploads') . '/imports/' . $import->file_path;
        $import->import_type = $this->input('import-type');
        $class = title_case($import->import_type);
        $classString = "App\\Importer\\{$class}Importer";
        Log::debug($classString);
        $importer = new $classString($filename);
        $import->field_map  = request('column-mappings');
        Log::debug($import->field_map);
        $import->save();
        $fieldMappings=[];

        if ($import->field_map) {
            foreach ($import->field_map as $field => $fieldValue) {
                $errorMessage = null;

                if(is_null($fieldValue)){
                    $errorMessage = trans('validation.import_field_empty');
                    $this->errorCallback($import, $field, $errorMessage);
                    
                    return $this->errors;
                }
            }
            // We submit as csv field: column, but the importer is happier if we flip it here.
            $fieldMappings = array_change_key_case(array_flip($import->field_map), CASE_LOWER);
                        // dd($fieldMappings);
        }
        $importer->setCallbacks([$this, 'log'], [$this, 'progress'], [$this, 'errorCallback'])
                 ->setUserId(Auth::id())
                 ->setUpdating($this->has('import-update'))
                 ->setShouldNotify($this->has('send-welcome'))
                 ->setUsernameFormat('firstname.lastname')
                 ->setFieldMappings($fieldMappings);
        // $logFile = storage_path('logs/importer.log');
        // \Log::useFiles($logFile);
        $importer->import();
        return $this->errors;
    }

    public function log($string)
    {
         \Log::Info($string);
    }

    public function progress($count)
    {
        // Open for future
        return;
    }
    public function errorCallback($item, $field, $errorString)
    {
        $this->errors[$item->name][$field] = $errorString;
    }

    private $errors;
}
