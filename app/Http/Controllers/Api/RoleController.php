<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;


class RoleController extends Controller
{
    public function index()
    {
        return Role::with('permissions')->get();
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);
        return Role::create($request->only('name'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|string']);
        $role->update($request->only('name'));
        return $role;
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate(['permission_ids' => 'array']);
        $role->permissions()->sync($request->permission_ids);
        return $role->load('permissions');
    }

    public function allPermissions()
    {
        return Permission::orderBy('module')->get()->groupBy('module');
    }
}
