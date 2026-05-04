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
      // Creating a simple form methods
    public function create()
    {
        return '
        <form method="POST" action="/expenses">

            <input type="hidden" name="_token" value="' . csrf_token() . '">

            <input type="text" name="title" placeholder="Expense Title"><br><br>

            <input type="number" step="0.01" name="amount" placeholder="Amount"><br><br>

            <input type="text" name="category" placeholder="Category"><br><br>

            <input type="date" name="expense_date"><br><br>

            <textarea name="description" placeholder="Description"></textarea><br><br>

            <button type="submit">Save Expense</button>
        </form>
        ';     
    }
      //Addding real insert logic
    public function store(Request $request)
    {
        Expense::create([
            'title'=> $request->title,
            'amount'=> $request->amount,
            'category'=> $request->category,
            'expense_date'=> $request->expense_date,
            'description'=> $request->description,
        ]);


        return "Expense added successfully";
    }
    //Added Delete Expense
    public function destroy($id)
    {
        $expense=Expense::findOrFail($id);

        $expense->delete();

        return "Expense deleted Successfully";
        
    }
    //Add Update Expense
    public function update(Request $request, $id)
    {
        $expense=Expense::findOrFail($id);

        $expense->update([
            'title'=> $request->title,
            'amount'=> $request->amount,
            'category'=> $request->category,
            'expense_date'=> $request->expense_date,
            'description'=> $request->description,
        ]);

        return "Expense updated successfully";
    
    }
    // Add Show Expense
    public function show($id)
    {
        return Expense::findOrFail($id);

    }
    //Add Edit Expense 
    public function edit($id)
    {
        return "Edit form for expense" . $id;

    }
        
    
}

