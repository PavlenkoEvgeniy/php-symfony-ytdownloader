<?php

declare(strict_types=1);

namespace App\Enum;

enum VideoDownloaderOption: string
{
    case BEST_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    case MODERATE_VIDEO_DOWNLOAD_FORMAT = 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    case POOR_VIDEO_DOWNLOAD_FORMAT     = 'bestvideo[height<=320][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    case NO_VIDEO_DOWNLOAD_FORMAT       = 'bestaudio/best';
    case OUTPUT_FILE_FORMAT_VIDEO       = '%(title)s-%(height)sp.%(ext)s';
    case OUTPUT_FILE_FORMAT_AUDIO       = '%(title)s.%(ext)s';
    case MERGE_OUTPUT_FORMAT_VIDEO      = 'mp4';
    case FORMAT_AUDIO                   = 'mp3';
}
