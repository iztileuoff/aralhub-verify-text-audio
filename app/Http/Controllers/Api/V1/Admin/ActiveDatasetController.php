<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\FileResource;
use App\Models\File;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Files & Datasets', weight: 60)]
class ActiveDatasetController extends Controller
{
    /**
     * The dataset every queue, counter and report is currently scoped to.
     * Saves the admin panel from paging through the file list to find it.
     */
    public function __invoke(): FileResource|JsonResponse
    {
        $file = File::query()->active()->with('user')->first();

        if ($file === null) {
            $configured = (int) config('dataset.main_file_id');

            return response()->json([
                'message' => "Active dataset (file #{$configured}) does not exist.",
            ], 404);
        }

        return new FileResource($file);
    }
}
