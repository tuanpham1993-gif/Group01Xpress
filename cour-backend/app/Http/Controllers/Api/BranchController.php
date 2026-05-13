<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    // GET ALL
    public function index()
    {
        return response()->json(
            Branch::orderBy('id', 'desc')->get()
        );
    }

    // CREATE
    public function store(Request $request)
    {
        $branch = Branch::create([
            'branch_name' => $request->branch_name,
            'city' => $request->city,
            'address' => $request->address,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Branch added successfully',
            'data' => $branch
        ]);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);

        $branch->update([
            'branch_name' => $request->branch_name,
            'city' => $request->city,
            'address' => $request->address,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Updated successfully'
        ]);
    }

    // DELETE
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);

        $branch->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}