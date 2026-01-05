<?php

namespace AlyIcom\MyPackage\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckUpdateController extends Controller
{
    protected $checkUpdateModel;

    public function __construct()
    {
        $this->checkUpdateModel = config('my-package.models.check_update', 'App\Models\CheckUpdate');
    }

    public function get()
    {
        $checkUpdateClass = $this->checkUpdateModel;
        $update = $checkUpdateClass::getInstance();
        
        return response()->json([
            'message' => 'Data Retrieved Successfully',
            'data' => $update
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ios_version' => 'nullable|integer|min:0|max:255',
            'android_version' => 'nullable|integer|min:0|max:255',
            'ios' => 'nullable|boolean',
            'android' => 'nullable|boolean',
            'android_link' => 'nullable|string|max:255',
            'ios_link' => 'nullable|string|max:255',
        ]);

        $checkUpdateClass = $this->checkUpdateModel;
        $update = $checkUpdateClass::getInstance();
        $update->updateInstance($validated);

        return response()->json([
            'message' => 'Update configuration modified successfully',
            'data' => $checkUpdateClass::getInstance()
        ]);
    }
}

