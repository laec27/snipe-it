<?php
namespace App\Http\Controllers\Kits;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Models\PredefinedKit;
use App\Models\PredefinedLicence;
use App\Models\PredefinedModel;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
/**
 * This controller handles all access kits management:
 * list, add/remove/change
 *
 * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
 */
class PredefinedKitsController extends Controller
{
    /**
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @return \Illuminate\Contracts\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('index', PredefinedKit::class);
        return view('kits/index');
    }

    /**
     *  Returns a form view to create a new kit.
     * 
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return mixed
     */
    public function create()
    {
        $this->authorize('create', PredefinedKit::class);
        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }
        return view('kits/create')->with('item', new PredefinedKit)
        ->with('groups',$userGroups);
    }

    /**
     * Validate and process the new Predefined Kit data.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @return Redirect
     */
    public function store(ImageUploadRequest $request)
    {
        $this->authorize('create', PredefinedKit::class);
        // Create a new Predefined Kit
        $kit = new PredefinedKit;
        $kit->name = $request->input('name');

        if (!$kit->save()) {
            return redirect()->back()->withInput()->withErrors($kit->getErrors());
        }
        $success = $kit->save();
        if (!$success) {
            return redirect()->back()->withInput()->withErrors($kit->getErrors());
        }
        $kit->groups()->sync($request->input('groups'),false);
        return redirect()->route("kits.index")->with('success', 'Kit was successfully created.'); // TODO: trans()
    }

    /**
     * Returns a view containing the Predefined Kit edit form.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @since [v1.0]
     * @param int $kit_id
     * @return View
     */
    public function edit($kit_id=null)
    {
        $this->authorize('update', PredefinedKit::class);
        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }
        if ($kit = PredefinedKit::find($kit_id)) {

            $kitGrp= $kit->groups()->pluck('name', 'id')->toArray();
            
            $result = count(array_intersect($userGroups, $kitGrp));

            if($result|| $kit->user_id == Auth::id()){

                return view('kits/edit')
                    ->with('item', $kit)
                    ->with('models', $kit->models)
                    ->with('licenses', $kit->licenses)
                    ->with('groups',$userGroups);
            }else{
                return redirect()->back()->with('error', 'You can not edit');
            }
        }
        return redirect()->route('kits.index')->with('error', 'Kit does not exist');        // TODO: trans
    }


    /**
     * Validates and processes form data from the edit
     * Predefined Kit form based on the kit ID passed.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @since [v1.0]
     * @param int $kit_id
     * @return Redirect
     */
    public function update(ImageUploadRequest $request, $kit_id=null)
    {
        $this->authorize('update', PredefinedKit::class);
        // Check if the kit exists
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        $kit->name = $request->input('name');

        if ($kit->save()) {
            $kit->groups()->sync($request->input('groups'),false);
            return redirect()->route("kits.index")->with('success', 'Kit was successfully updated');        // TODO: trans
        }
        return redirect()->back()->withInput()->withErrors($kit->getErrors());
    }

    /**
     * Validate and delete the given Predefined Kit.
     * Also delete all contained helping items
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @since [v1.0]
     * @param int $kit_id
     * @return Redirect
     */
    public function destroy($kit_id)
    {
        $this->authorize('delete', PredefinedKit::class);
        // Check if the kit exists
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            return redirect()->route('kits.index')->with('error', 'Kit not found');     // TODO: trans
        }

        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }

        $kitGrp= $kit->groups()->pluck('name', 'id')->toArray();

        $result = count(array_intersect($userGroups, $kitGrp));

        if($result|| $kit->user_id == Auth::id()){
            // Delete childs
            $kit->models()->detach();
            $kit->licenses()->detach();
            $kit->consumables()->detach();
            $kit->accessories()->detach();
            // Delete the kit
            $kit->delete();
            // Redirect to the kit management page
            return redirect()->route('kits.index')->with('success', 'Kit was successfully deleted'); // TODO: trans
        }else{
            return redirect()->back()->with('error', 'You can not delete');
        }

        
    }

    /**
     * Get the kit information to present to the kit view page
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @since [v1.0]
     * @param int $modelId
     * @return View
     */
    public function show($kit_id=null)
    {
        return $this->edit($kit_id);
    }


    /**
     * Returns a view containing the Predefined Kit edit form.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @return View
     */
    public function editModel($kit_id, $model_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (($kit = PredefinedKit::find($kit_id))
            && ($model = $kit->models()->find($model_id))) {

            return view('kits/model-edit', [
                'kit' => $kit,
                'model' => $model,
                'item' => $model->pivot
            ]);
        }
        return redirect()->route('kits.index')->with('error', 'Kit does not exist');        // TODO: trans
    }

    /**
     * Get the kit information to present to the kit view page
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $modelId
     * @return View
     */
    public function updateModel(Request $request, $kit_id, $model_id)
    {

        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        $validator = \Validator::make($request->all(), $kit->makeModelRules($model_id));

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $pivot = $kit->models()->wherePivot('id', $request->input('pivot_id'))->first()->pivot;

        $pivot->model_id = $request->input('model_id');
        $pivot->quantity = $request->input('quantity');
        $pivot->save();

        return redirect()->route('kits.edit', $kit_id)->with('success', 'Model updated successfully.');     // TODO: trans
    }

    /**
     * Remove the model from set
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $modelId
     * @return View
     */
    public function detachModel($kit_id, $model_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        // Delete childs
        $kit->models()->detach($model_id);
        
        // Redirect to the kit management page
        return redirect()->route('kits.edit', $kit_id)->with('success', 'Model was successfully detached'); // TODO: trans
    }

    /**
     * Returns a view containing attached license edit form.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $license_id
     * @return View
     */
    public function editLicense($kit_id, $license_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (!($kit = PredefinedKit::find($kit_id))) {
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');        // TODO: trans
        }
        if (!($license = $kit->licenses()->find($license_id))) {
            return redirect()->route('kits.index')->with('error', 'License does not exist');        // TODO: trans
        }

        return view('kits/license-edit', [
            'kit' => $kit,
            'license' => $license,
            'item' => $license->pivot
        ]);
    }

    /**
     * Update attached licese
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $license_id
     * @return View
     */
    public function updateLicense(Request $request, $kit_id, $license_id)
    {

        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        $validator = \Validator::make($request->all(), $kit->makeLicenseRules($license_id));

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $pivot = $kit->licenses()->wherePivot('id', $request->input('pivot_id'))->first()->pivot;

        $pivot->license_id = $request->input('license_id');
        $pivot->quantity = $request->input('quantity');
        $pivot->save();

        return redirect()->route('kits.edit', $kit_id)->with('success', 'License updated successfully.');     // TODO: trans
    }

    /**
     * Remove the license from set
     * 
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $license_id
     * @return View
     */
    public function detachLicense($kit_id, $license_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        // Delete childs
        $kit->licenses()->detach($license_id);
        
        // Redirect to the kit management page
        return redirect()->route('kits.edit', $kit_id)->with('success', 'License was successfully detached'); // TODO: trans
    }


    /**
     * Returns a view containing attached accessory edit form.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $accessoryId
     * @return View
     */
    public function editAccessory($kit_id, $accessory_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (!($kit = PredefinedKit::find($kit_id))) {
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');        // TODO: trans
        }
        if (!($accessory = $kit->accessories()->find($accessory_id))) {
            return redirect()->route('kits.index')->with('error', 'Accessory does not exist');        // TODO: trans
        }

        return view('kits/accessory-edit', [
            'kit' => $kit,
            'accessory' => $accessory,
            'item' => $accessory->pivot
        ]);
    }

    /**
     * Update attached accessory
     * 
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $accessory_id
     * @return View
     */
    public function updateAccessory(Request $request, $kit_id, $accessory_id)
    {

        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        $validator = \Validator::make($request->all(), $kit->makeAccessoryRules($accessory_id));

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $pivot = $kit->accessories()->wherePivot('id', $request->input('pivot_id'))->first()->pivot;

        $pivot->accessory_id = $request->input('accessory_id');
        $pivot->quantity = $request->input('quantity');
        $pivot->save();

        return redirect()->route('kits.edit', $kit_id)->with('success', 'Accessory updated successfully.');     // TODO: trans
    }

    /**
     * Remove the accessory from set
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $accessory_id
     * @return View
     */
    public function detachAccessory($kit_id, $accessory_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        // Delete childs
        $kit->accessories()->detach($accessory_id);
        
        // Redirect to the kit management page
        return redirect()->route('kits.edit', $kit_id)->with('success', 'Accessory was successfully detached'); // TODO: trans
    }

    /**
     * Returns a view containing attached consumable edit form.
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $consumable_id
     * @return View
     */
    public function editConsumable($kit_id, $consumable_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (!($kit = PredefinedKit::find($kit_id))) {
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');        // TODO: trans
        }
        if (!($consumable = $kit->consumables()->find($consumable_id))) {
            return redirect()->route('kits.index')->with('error', 'Consumable does not exist');        // TODO: trans
        }

        return view('kits/consumable-edit', [
            'kit' => $kit,
            'consumable' => $consumable,
            'item' => $consumable->pivot
        ]);
    }

    /**
     * Update attached consumable
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $kit_id
     * @param int $consumableId
     * @return View
     */
    public function updateConsumable(Request $request, $kit_id, $consumable_id)
    {

        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        $validator = \Validator::make($request->all(), $kit->makeConsumableRules($consumable_id));

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $pivot = $kit->consumables()->wherePivot('id', $request->input('pivot_id'))->first()->pivot;

        $pivot->consumable_id = $request->input('consumable_id');
        $pivot->quantity = $request->input('quantity');
        $pivot->save();

        return redirect()->route('kits.edit', $kit_id)->with('success', 'Consumable updated successfully.');     // TODO: trans
    }

    /**
     * Remove the consumable from set
     *
     * @author [D. Minaev] [<dmitriy.minaev.v@gmail.com>]
     * @param int $consumable_id
     * @return View
     */
    public function detachConsumable($kit_id, $consumable_id)
    {
        $this->authorize('update', PredefinedKit::class);
        if (is_null($kit = PredefinedKit::find($kit_id))) {
            // Redirect to the kits management page
            return redirect()->route('kits.index')->with('error', 'Kit does not exist');      // TODO: trans
        }

        // Delete childs
        $kit->consumables()->detach($consumable_id);
        
        // Redirect to the kit management page
        return redirect()->route('kits.edit', $kit_id)->with('success', 'Consumable was successfully detached'); // TODO: trans
    }
}
