<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    //
    public function index()
    {
        try {
            // Lấy danh sách hóa đơn kèm thông tin shipment và details
            $invoices = Invoice::with('shipment.details')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $invoices
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
