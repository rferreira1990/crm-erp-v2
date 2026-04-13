<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleMediaController extends Controller
{
    public function showImage(Request $request, int $article, int $articleImage): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;

        $image = ArticleImage::query()
            ->where('company_id', $companyId)
            ->where('article_id', $article)
            ->whereKey($articleImage)
            ->firstOrFail();

        return Storage::disk('local')->response($image->file_path, $image->original_name);
    }

    public function showFile(Request $request, int $article, int $articleFile): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;

        $file = ArticleFile::query()
            ->where('company_id', $companyId)
            ->where('article_id', $article)
            ->whereKey($articleFile)
            ->firstOrFail();

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }
}

