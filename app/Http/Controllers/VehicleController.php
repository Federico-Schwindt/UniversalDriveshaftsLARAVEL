<?php

namespace App\Http\Controllers;

use App\Services\VehiclePages;

/** Server-rendered vehicle landing pages: /driveshafts, /driveshafts/{make}, /driveshafts/{make}/{model}. */
class VehicleController extends Controller
{
    public function __construct(private VehiclePages $veh) {}

    public function hub()
    {
        return view('vehicles.hub', $this->veh->hub());
    }

    public function make(string $make)
    {
        $name = $this->veh->makeFromSlug($make);
        abort_if(!$name, 404);
        return view('vehicles.make', $this->veh->make($name));
    }

    public function model(string $make, string $model)
    {
        $makeName = $this->veh->makeFromSlug($make);
        abort_if(!$makeName, 404);
        $modelName = $this->veh->modelFromSlug($makeName, $model);
        abort_if(!$modelName, 404);
        return view('vehicles.model', $this->veh->model($makeName, $modelName));
    }
}
