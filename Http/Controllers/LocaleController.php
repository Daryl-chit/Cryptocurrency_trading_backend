<?php

namespace App\Http\Controllers;

/**
 * Class LocaleController
 * @package App\Http\Controllers
*/
class LocaleController extends Controller
{
    /**
     * @param $locale
     * @return \Illuminate\Http\RedirectResponse
    */
    public function swap($locale) {
        session()->put('locale', $locale);
        return redirect()->back();
    }
}
