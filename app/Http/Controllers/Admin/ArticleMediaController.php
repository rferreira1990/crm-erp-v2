<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleMediaController extends Controller
{
    public function showImage(Request $request, int $article, int $articleImage): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('view', $articleModel);

        $image = ArticleImage::query()
            ->where('company_id', $companyId)
            ->where('article_id', $articleModel->id)
            ->whereKey($articleImage)
            ->firstOrFail();

        return $this->localDiskResponse(
            (string) $image->file_path,
            (string) $image->original_name,
            ['articles/'.$companyId.'/'.$articleModel->id.'/images']
        );
    }

    public function showFile(Request $request, int $article, int $articleFile): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('view', $articleModel);

        $file = ArticleFile::query()
            ->where('company_id', $companyId)
            ->where('article_id', $articleModel->id)
            ->whereKey($articleFile)
            ->firstOrFail();

        return $this->localDiskDownload(
            (string) $file->file_path,
            (string) $file->original_name,
            ['articles/'.$companyId.'/'.$articleModel->id.'/files']
        );
    }

    private function findCompanyArticleOrFail(int $companyId, int $articleId): Article
    {
        return Article::query()
            ->forCompany($companyId)
            ->whereKey($articleId)
            ->firstOrFail();
    }
}
