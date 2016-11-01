<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function create_tt(){
    	return view('ticket.create_tt');
    }
}
