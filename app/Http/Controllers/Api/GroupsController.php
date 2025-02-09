<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\GroupsTransformer;
use App\Models\Group;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Log;

class GroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view', Group::class);
        
        $myArr = array();
        $userData = Auth::user()->isAdminofGroup();

        foreach($userData as $userGroup){
            array_push($myArr,$userGroup);
        }

        $allowed_columns = ['id','name','created_at', 'users_count'];

        $groups = Group::select('id','name','permissions','created_at','updated_at')->withCount('users as users_count');

        if ($request->filled('search')) {
            $groups = $groups->TextSearch($request->input('search'));
        }

        if(Auth::user()->isSuperUser()){
           
        }else{
            $groups->whereIn('name', $myArr);
        }

        // Set the offset to the API call's offset, unless the offset is higher than the actual count of items in which
        // case we override with the actual count, so we should return 0 items.
        $offset = (($groups) && ($request->get('offset') > $groups->count())) ? $groups->count() : $request->get('offset', 0);

        // Check to make sure the limit is not higher than the max allowed
        ((config('app.max_results') >= $request->input('limit')) && ($request->filled('limit'))) ? $limit = $request->input('limit') : $limit = config('app.max_results');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';
        $groups->orderBy($sort, $order);

        $total = $groups->count();
        $groups = $groups->skip($offset)->take($limit)->get();
        return (new GroupsTransformer)->transformGroups($groups, $total);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Group::class);
        $group = new Group;
        $group->fill($request->all());

        if ($group->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $group, trans('admin/groups/message.create.success')));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, $group->getErrors()));

    }

    /**
     * Display the specified resource.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('view', Group::class);
        $group = Group::findOrFail($id);
        return (new GroupsTransformer)->transformGroup($group);
    }


    /**
     * Update the specified resource in storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', Group::class);
        $group = Group::findOrFail($id);
        $group->fill($request->all());

        if ($group->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $group, trans('admin/groups/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $group->getErrors()));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.0]
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('delete', Group::class);
        $group = Group::findOrFail($id);
        $this->authorize('delete', $group);
        $group->delete();
        return response()->json(Helper::formatStandardApiResponse('success', null,  trans('admin/groups/message.delete.success')));

    }


}
