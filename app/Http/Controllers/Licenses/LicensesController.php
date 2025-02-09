<?php
namespace App\Http\Controllers\Licenses;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

/**
 * This controller handles all actions related to Licenses for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class LicensesController extends Controller
{

    /**
     * Returns a view that invokes the ajax tables which actually contains
     * the content for the licenses listing, which is generated in getDatatable.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see LicensesController::getDatatable() method that generates the JSON response
     * @since [v1.0]
     * @return \Illuminate\Contracts\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        $this->authorize('view', License::class);
        return view('licenses/index');
    }


    /**
     * Returns a form view that allows an admin to create a new licence.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see AccessoriesController::getDatatable() method that generates the JSON response
     * @since [v1.0]
     * @return \Illuminate\Contracts\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', License::class);
        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }
        $maintained_list = [
            '' => 'Maintained',
            '1' => 'Yes',
            '0' => 'No'
        ];

        return view('licenses/edit')
            ->with('depreciation_list', Helper::depreciationList())
            ->with('maintained_list', $maintained_list)
            ->with('item', new License)
            ->with('groups',$userGroups);

    }


    /**
     * Validates and stores the license form data submitted from the new
     * license form.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see LicensesController::getCreate() method that provides the form view
     * @since [v1.0]
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', License::class);
        // create a new model instance
        $license = new License();
        // Save the license data
        $license->company_id        = Company::getIdForCurrentUser($request->input('company_id'));
        $license->depreciation_id   = $request->input('depreciation_id');
        $license->expiration_date   = $request->input('expiration_date');
        $license->license_email     = $request->input('license_email');
        $license->license_name      = $request->input('license_name');
        $license->maintained        = $request->input('maintained', 0);
        $license->manufacturer_id   = $request->input('manufacturer_id');
        $license->name              = $request->input('name');
        $license->notes             = $request->input('notes');
        $license->order_number      = $request->input('order_number');
        $license->purchase_cost     = Helper::ParseCurrency($request->input('purchase_cost'));
        $license->purchase_date     = $request->input('purchase_date');
        $license->purchase_order    = $request->input('purchase_order');
        $license->purchase_order    = $request->input('purchase_order');
        $license->reassignable      = $request->input('reassignable', 0);
        $license->seats             = $request->input('seats');
        $license->serial            = $request->input('serial');
        $license->supplier_id       = $request->input('supplier_id');
        $license->category_id       = $request->input('category_id');
        $license->termination_date  = $request->input('termination_date');
        $license->user_id           = Auth::id();

        if ($license->save()) {
            $license->groups()->sync($request->input('groups'),false);
            return redirect()->route("licenses.index")->with('success', trans('admin/licenses/message.create.success'));
        }
        return redirect()->back()->withInput()->withErrors($license->getErrors());
    }

    /**
     * Returns a form with existing license data to allow an admin to
     * update license information.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     * @param int $licenseId
     * @return \Illuminate\Contracts\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($licenseId = null)
    {
        if (is_null($item = License::find($licenseId))) {
            return redirect()->route('licenses.index')->with('error', trans('admin/licenses/message.does_not_exist'));
        }

        $this->authorize('update', $item);

        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }

        $licenseGrp= $item->groups()->pluck('name', 'id')->toArray();
            
        $result = count(array_intersect($userGroups, $licenseGrp));

        if($result|| $item->user_id == Auth::id()){

            $maintained_list = [
                '' => 'Maintained',
                '1' => 'Yes',
                '0' => 'No'
            ];

            return view('licenses/edit', compact('item'))
                ->with('depreciation_list', Helper::depreciationList())
                ->with('maintained_list', $maintained_list)
                ->with('groups',$userGroups);
        }else{
            return redirect()->back()->with('error', 'You can not edit');
        }
    }


    /**
     * Validates and stores the license form data submitted from the edit
     * license form.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see LicensesController::getEdit() method that provides the form view
     * @since [v1.0]
     * @param Request $request
     * @param int $licenseId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, $licenseId = null)
    {
        if (is_null($license = License::find($licenseId))) {
            return redirect()->route('licenses.index')->with('error', trans('admin/licenses/message.does_not_exist'));
        }

        $this->authorize('update', $license);

        $license->company_id        = Company::getIdForCurrentUser($request->input('company_id'));
        $license->depreciation_id   = $request->input('depreciation_id');
        $license->expiration_date   = $request->input('expiration_date');
        $license->license_email     = $request->input('license_email');
        $license->license_name      = $request->input('license_name');
        $license->maintained        = $request->input('maintained',0);
        $license->name              = $request->input('name');
        $license->notes             = $request->input('notes');
        $license->order_number      = $request->input('order_number');
        $license->purchase_cost     = Helper::ParseCurrency($request->input('purchase_cost'));
        $license->purchase_date     = $request->input('purchase_date');
        $license->purchase_order    = $request->input('purchase_order');
        $license->reassignable      = $request->input('reassignable', 0);
        $license->serial            = $request->input('serial');
        $license->termination_date  = $request->input('termination_date');
        $license->seats             = e($request->input('seats'));
        $license->manufacturer_id   =  $request->input('manufacturer_id');
        $license->supplier_id       = $request->input('supplier_id');
        $license->category_id       = $request->input('category_id');

        if ($license->save()) {
            $license->groups()->sync($request->input('groups'),false);
            return redirect()->route('licenses.show', ['license' => $licenseId])->with('success', trans('admin/licenses/message.update.success'));
        }
        // If we can't adjust the number of seats, the error is flashed to the session by the event handler in License.php
        return redirect()->back()->withInput()->withErrors($license->getErrors());
    }

    /**
     * Checks to see whether the selected license can be deleted, and
     * if it can, marks it as deleted.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     * @param int $licenseId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy($licenseId)
    {
        // Check if the license exists
        if (is_null($license = License::find($licenseId))) {
            // Redirect to the license management page
            return redirect()->route('licenses.index')->with('error', trans('admin/licenses/message.not_found'));
        }

        $this->authorize('delete', $license);

        $user =  User::find(Auth::id());

        if($user->isSuperUser()){
            $userGroups = Group::pluck('name', 'id')->toArray();
        }else{
            $userGroups = $user->isAdminofGroup();
        }

        $licenseGrp= $license->groups()->pluck('name', 'id')->toArray();

        $result = count(array_intersect($userGroups, $licenseGrp));

        if($result|| $license->user_id == Auth::id()){
            if ($license->assigned_seats_count == 0) {
                // Delete the license and the associated license seats
                DB::table('license_seats')
                    ->where('id', $license->id)
                    ->update(array('assigned_to' => null,'asset_id' => null));

                $licenseSeats = $license->licenseseats();
                $licenseSeats->delete();
                $license->delete();

                // Redirect to the licenses management page
                return redirect()->route('licenses.index')->with('success', trans('admin/licenses/message.delete.success'));
                // Redirect to the license management page
            }
            return redirect()->route('licenses.index')->with('error', trans('admin/licenses/message.assoc_users'));
        }else{
            return redirect()->back()->with('error', 'You can not delete');
        }
        // There are still licenses in use.

    }


    /**
     * Makes the license detail page.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     * @param int $licenseId
     * @return \Illuminate\Contracts\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($licenseId = null)
    {

        $license = License::with('assignedusers', 'licenseSeats.user', 'licenseSeats.asset')->find($licenseId);

        if ($license) {
            $this->authorize('view', $license);
            return view('licenses/view', compact('license'));
        }
        return redirect()->route('licenses.index')
            ->with('error', trans('admin/licenses/message.does_not_exist'));
    }
    

    public function getClone($licenseId = null)
    {
        if (is_null($license_to_clone = License::find($licenseId))) {
            return redirect()->route('licenses.index')->with('error', trans('admin/licenses/message.does_not_exist'));
        }

        $this->authorize('create', License::class);

        $maintained_list = [
            '' => 'Maintained',
            '1' => 'Yes',
            '0' => 'No'
        ];
        //clone the orig
        $license = clone $license_to_clone;
        $license->id = null;
        $license->serial = null;

        // Show the page
        return view('licenses/edit')
        ->with('depreciation_list', Helper::depreciationList())
        ->with('item', $license)
        ->with('maintained_list', $maintained_list);
    }
}
