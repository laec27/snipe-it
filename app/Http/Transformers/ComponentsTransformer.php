<?php
namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Component;
use Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ComponentsTransformer
{
    public function transformComponents(Collection $components, $total)
    {
        $array = array();
        foreach ($components as $component) {
            $array[] = self::transformComponent($component);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformComponent(Component $component)
    {
        $array = [
            'id' => (int) $component->id,
            'name' => e($component->name),
            'image' =>   ($component->image) ? Storage::disk('public')->url('components/'.e($component->image)) : null,
            'serial' => ($component->serial) ? e($component->serial) : null,
            'location' => ($component->location) ? [
                'id' => (int) $component->location->id,
                'name' => e($component->location->name)
            ] : null,
            'qty' => ($component->qty!='') ? (int) $component->qty : null,
            'min_amt' => ($component->min_amt!='') ? (int) $component->min_amt : null,
            'category' => ($component->category) ? [
                'id' => (int) $component->category->id,
                'name' => e($component->category->name)
            ] : null,
            'order_number'  => e($component->order_number),
            'purchase_date' =>  Helper::getFormattedDateObject($component->purchase_date, 'date'),
            'purchase_cost' => Helper::formatCurrencyOutput($component->purchase_cost),
            'remaining'  => (int) $component->numRemaining(),
            'company'   => ($component->company) ? [
                'id' => (int) $component->company->id,
                'name' => e($component->company->name)
            ] : null,
            'created_at' => Helper::getFormattedDateObject($component->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($component->updated_at, 'datetime'),
            'user_can_checkout' =>  ($component->numRemaining() > 0) ? 1 : 0,
        ];

        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', Component::class),
            'checkin' => Gate::allows('checkin', Component::class),
            'update' => Gate::allows('update', Component::class),
            'delete' => Gate::allows('delete', Component::class),
        ];
        $array += $permissions_array;

        $numGroups = $component->groups->count();
        if($numGroups > 0)
        {
            $groups["total"] = $numGroups; 
            
            foreach($component->groups as $group)
            {
                if(!Auth::user()->isSuperUser()){
                    $user_groups = Auth::user()->groups;
                    if($user_groups->contains('id',$group->id)){
                        $groups["rows"][] = [
                            'id' => (int) $group->id,
                            'name' => e($group->name)
                        ];
                    }
                }else{
                    $groups["rows"][] = [
                        'id' => (int) $group->id,
                        'name' => e($group->name)
                    ];
                }
            }
            $array["groups"] = $groups;
        }
        else {
            $array["groups"] = null;
        }

        return $array;
    }


    public function transformCheckedoutComponents(Collection $components_assets, $total)
    {
        $array = array();
        foreach ($components_assets as $asset) {
            $array[] = [
                'assigned_pivot_id' => $asset->pivot->id,
                'id' => (int) $asset->id,
                'name' =>  e($asset->model->present()->name) .' '.e($asset->present()->name),
                'qty' => $asset->pivot->assigned_qty,
                'type' => 'asset',
                'created_at' => Helper::getFormattedDateObject($asset->pivot->created_at, 'datetime'),
                'available_actions' => ['checkin' => true]
            ];

        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }
}
