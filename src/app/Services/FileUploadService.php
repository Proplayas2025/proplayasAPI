<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService
{
    public static function uploadImage(UploadedFile $image, string $folder, ?string $oldFilename = null): string
    {
        $filename = Str::uuid() . '.webp';
        $path = "uploads/{$folder}/{$filename}";
    
        $manager = new ImageManager(new Driver());
        $webp = $manager->read($image->getPathname())->toWebp(85);
    
        Storage::disk('public')->put($path, $webp);
    
        // Eliminar archivo antiguo si existe
        if ($oldFilename && !filter_var($oldFilename, FILTER_VALIDATE_URL)) {
            static::delete($oldFilename, $folder);
        }
    
        return $filename;
    }

    public static function uploadFile(UploadedFile $file, string $folder, ?string $oldFilename = null): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "uploads/{$folder}/{$filename}";
    
        Storage::disk('public')->putFileAs("uploads/{$folder}", $file, $filename);
    
        // Eliminar archivo antiguo si existe
        if ($oldFilename && !filter_var($oldFilename, FILTER_VALIDATE_URL)) {
            static::delete($oldFilename, $folder);
        }
    
        return $filename;
    }

    public static function delete(?string $filename, string $folder = ''): void
    {
        if (!$filename) return;
        
        // Si es una URL externa, no hacer nada
        if (filter_var($filename, FILTER_VALIDATE_URL)) return;
        
        // Si no se especifica folder, intentar inferirlo o usar path completo
        if (!$folder) {
            // Si el filename contiene la estructura uploads/..., usarlo directamente
            $path = str_starts_with($filename, 'uploads/') ? $filename : "uploads/{$filename}";
        } else {
            // Construir el path con folder
            $path = "uploads/{$folder}/" . basename($filename);
        }
        
        Storage::disk('public')->delete($path);
    }    
}