<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateSetting;
use Illuminate\Http\Request;

class CertificateSettingController extends Controller
{
    public function show()
    {
        return response()->json(CertificateSetting::current());
    }

    public function update(Request $request)
    {
        $rules = array_fill_keys(CertificateSetting::FIELDS, 'nullable|string|max:255');
        $settings = CertificateSetting::current();
        $settings->update($request->validate($rules));

        return response()->json($settings);
    }
}
