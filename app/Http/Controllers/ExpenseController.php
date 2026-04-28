<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::all();
    }

    public function create()
    {
        return "Show create form";
    }

    public function store(Request $request)
    {
        return "Save expense";
    }
}