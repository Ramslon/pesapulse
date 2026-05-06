<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::where('user_id', auth::id())->get();
    }
    // Adding search expenses
    public function search (Request $request)
    {
        $query = Expense::where('user_id', Auth::id());

        if ($request->title) {
        $query->where('title', 'LIKE', '%' . $request->title . '%');

        if ($request->category) {
        $query->where('category', 'LIKE', '%' . $request->category . '%');
    }
    
        return response()->json(
        $query->get()
    );

    }

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
        $expense=Expense::create([
            'user_id' => auth::id(),
            'title'=> $request->title,
            'amount'=> $request->amount,
            'category'=> $request->category,
            'expense_date'=> $request->expense_date,
            'description'=> $request->description,
        ]);


        return response()->json($expense);
    }
    //Added Delete Expense
    
    public function destroy($id)
    {
        $expense = Expense::where('user_id', Auth::id())
                      ->findOrFail($id);

        $expense->delete();

        return response()->json([
             'message' => 'Expense deleted successfully'
        ]);
    }
    //Add Update Expense

    public function update(Request $request, $id)
    {
        $request->validate([
             'title' => 'required',
             'amount' => 'required|numeric',
             'category' => 'required',
             'expense_date' => 'required|date',
        ]);

        $expense = Expense::where('user_id', Auth::id())->findOrFail($id);

        $expense->update([
             'title' => $request->title,
             'amount' => $request->amount,
             'category' => $request->category,
             'expense_date' => $request->expense_date,
             'description' => $request->description,
        ]);

        return response()->json([
             'message' => 'Expense updated successfully',
             'expense' => $expense
        ]);
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

