<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Design;
use App\Models\Event;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class DesignController extends Controller
{

    // public function __construct()
    // {
    //     $this->middleware('jwt.verify', ['except' => ['index', 'show']]);
    // }


    // public function index(Request $request)
    // {
    //     $perPage = $request->input('per_page', 4); // Get per_page value from request or default to 10
    //     $designs = Design::with('event')->paginate($perPage);
    
    //     // Array to store the designs along with their event details and images
    //     $designData = [];
    
    //     foreach ($designs as $design) {
    //         // Get the event associated with the design
    //         $event = $design->event;
    
    //         // Check if the event was found
    //         if (!$event) {
    //             continue; // Skip designs without an associated event
    //         }
    
    //         // Get the event title
    //         $eventTitle = $event->title;
    
    //         // Define the directory path based on the event title
    //         $directoryPath = 'public/' . str_replace(' ', '-', strtolower($eventTitle));
    
    //         // Initialize an array to store Base64-encoded images for the current design
    //         $imageData = [];
    
    //         // Check if the directory exists
    //         if (Storage::disk('local')->exists($directoryPath)) {
    //             // Get all files in the directory
    //             $files = Storage::disk('local')->files($directoryPath);
    
    //             // Iterate through each file and convert to Base64
    //             foreach ($files as $filePath) {
    //                 // Read the image content from local storage
    //                 $imageContent = Storage::disk('local')->get($filePath);
    
    //                 // Convert the image content to Base64 format
    //                 $base64Image = base64_encode($imageContent);
    
    //                 // Add the Base64-encoded image to the image data array
    //                 $imageData[] = $base64Image;
    //             }
    //         } else {
    //             Log::info('Directory not found: ' . $directoryPath);
    //         }
    
    //         // Add the design and its images to the design data array
    //         $designData[] = [
    //             'design_id' => $design->id,
    //             'event_id' => $event->id,
    //             'title' => $eventTitle,
    //             'description' => $event->description,
    //             'images' => $imageData
    //         ];
    //     }
    
    //     // Return the retrieved design data as JSON with pagination metadata
    //     return response()->json([
    //         'Design' => $designData,
    //         'pagination' => [
    //             'total' => $designs->total(),
    //             'per_page' => $designs->perPage(),
    //             'current_page' => $designs->currentPage(),
    //             'last_page' => $designs->lastPage(),
    //             'from' => $designs->firstItem(),
    //             'to' => $designs->lastItem(),
    //         ]
    //     ], 200);
    // }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 4); 
        $designs = Design::with('event')->paginate($perPage);

        $designData = [];

        foreach ($designs as $design) {
            $event = $design->event;

            if (!$event) {
                continue; 
            }

            $designData[] = [
                'design_id' => $design->id,
                'event_id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
            ];
        }

        return response()->json([
            'Design' => $designData,
            'pagination' => [
                'total' => $designs->total(),
                'per_page' => $designs->perPage(),
                'current_page' => $designs->currentPage(),
                'last_page' => $designs->lastPage(),
                'from' => $designs->firstItem(),
                'to' => $designs->lastItem(),
            ]
        ], 200);
    }


    public function getDesignImages($designId)
    {
        $design = Design::find($designId);

        if (!$design) {
            return response()->json(['message' => 'Design not found'], 404);
        }

        $event = $design->event;

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $eventTitle = $event->title;

        $directoryPath = 'public/' . str_replace(' ', '-', strtolower($eventTitle));

        $imageData = [];

        if (Storage::disk('local')->exists($directoryPath)) {
            $files = Storage::disk('local')->files($directoryPath);

            foreach ($files as $filePath) {
                $imageContent = Storage::disk('local')->get($filePath);

                $base64Image = base64_encode($imageContent);

                $imageData[] = $base64Image;
            }
        } else {
            Log::info('Directory not found: ' . $directoryPath);
        }

        return response()->json(['images' => $imageData], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'event_id' => 'required|exists:events,id',
            'images' => 'required|array',
            'images.*' => 'required|string', 
        ]);

        $event = Event::findOrFail($validatedData['event_id']);

        $eventTitle = $event->title;

        $folderName = Str::slug($eventTitle);

        $folderPath = 'public/' . $folderName;
        if (!Storage::disk('local')->exists($folderPath)) {
            Storage::disk('local')->makeDirectory($folderPath);
        }

        $storedImagePaths = [];
        foreach ($validatedData['images'] as $base64Image) {
            $filename = uniqid() . '_' . time() . '.png'; 

            $decodedImage = base64_decode($base64Image);

            Storage::disk('local')->put($folderPath . '/' . $filename, $decodedImage);

            $storedImagePaths[] = $folderName . '/' . $filename; 
        }

        $eventDesign = new Design();
        $eventDesign->event_id = $validatedData['event_id'];
        $eventDesign->images = json_encode($storedImagePaths);

        $eventDesign->save();

        return response()->json($eventDesign, 201);
    }

public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'event_id' => 'required|exists:events,id',
            'images' => 'required|array',
            'images.*' => 'required|string', 
        ]);

        $eventDesign = Design::findOrFail($id);

        $event = Event::findOrFail($validatedData['event_id']);

        $eventTitle = $event->title;

        $folderName = Str::slug($eventTitle); 

        $folderPath = 'public/' . $folderName;
        if (!Storage::disk('local')->exists($folderPath)) {
            Storage::disk('local')->makeDirectory($folderPath);
        }

        $existingImages = json_decode($eventDesign->images, true);

        $newImagePaths = [];
        $decodedImages = [];
        foreach ($validatedData['images'] as $base64Image) {
            $filename = uniqid() . '_' . time() . '.png'; 

            $decodedImage = base64_decode($base64Image);

            Storage::disk('local')->put($folderPath . '/' . $filename, $decodedImage);

            $newImagePaths[] = $folderName . '/' . $filename; 

            $decodedImages[] = $folderPath . '/' . $filename;
        }

        $imagesToDelete = array_diff($existingImages, $newImagePaths);

        foreach ($imagesToDelete as $imagePath) {
            Storage::disk('local')->delete('public/' . $imagePath);
        }

        $eventDesign->event_id = $validatedData['event_id'];
        $eventDesign->images = json_encode($newImagePaths);
        $eventDesign->save();

        return response()->json($eventDesign);
    }
// public function update(Request $request, $id)
// {
//     \Log::info('Request Data:', $request->all());

//     $validatedData = $request->validate([
//         'event_id' => 'required|exists:events,id',
//       'images' => 'required|array',
//             'images.*' => 'required|string', 
//     ]);

//     $eventDesign = Design::findOrFail($id);

//     $event = Event::findOrFail($validatedData['event_id']);
//     $eventTitle = $event->title;
//     $folderName = Str::slug($eventTitle);
//     $folderPath = 'public/' . $folderName;

//     if (!Storage::disk('local')->exists($folderPath)) {
//         Storage::disk('local')->makeDirectory($folderPath);
//     }

//     $storedImagePaths = json_decode($eventDesign->images, true) ?? [];

//     if ($request->hasFile('images')) {
//         foreach ($request->file('images') as $image) {
//             $filename = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
//             $image->storeAs($folderPath, $filename, 'local');
//             $storedImagePaths[] = $folderName . '/' . $filename;
//         }
//     }

//     $eventDesign->event_id = $validatedData['event_id'];
//     $eventDesign->images = json_encode($storedImagePaths);
//     $eventDesign->save();

//     return response()->json($eventDesign, 200);
// }

    
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $eventTitle = $event->title;

        $directoryPath = 'public/' . str_replace(' ', '-', strtolower($eventTitle));

        if (!Storage::disk('local')->exists($directoryPath)) {
            return response()->json(['status' => 'failed', 'message' => 'No Designs available for this Event.'], 200);
        }

        $files = Storage::disk('local')->files($directoryPath);

        $imageData = [];

        foreach ($files as $filePath) {
            $imageContent = Storage::disk('local')->get($filePath);

            $base64Image = base64_encode($imageContent);

            $imageData[] = $base64Image;
        }

        return response()->json(['event_id' => $id, 'title' => $eventTitle, 'images' => $imageData], 200);
    }

    /**
     * Update the specified resource in storage.
     */
   
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $eventDesign = Design::find($id);

        if (!$eventDesign) {
            return response()->json(['message' => 'Event design not found.'], 404);
        }

        $event = Event::find($eventDesign->event_id);

        $eventTitle = $event->title;

        $directoryPath = 'public/' . str_replace(' ', '-', strtolower($eventTitle));

        if (Storage::disk('local')->exists($directoryPath)) {
            Storage::disk('local')->deleteDirectory($directoryPath);
        }

        $eventDesign->delete();

        return response()->json(['message' => 'Event design and associated images deleted successfully.'], 200);
    }

    public function image(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->storeAs('public/images', $file->getClientOriginalName());
            
            return response()->json(['filePath' => Storage::url($path)], 200);
        }

        return response()->json(['error' => 'File not uploaded'], 400);
    }

   
}

