<?php

namespace App\Services;

use App\Jobs\ProcessResume;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreResumeService
{
    public function execute(User $user, UploadedFile $file, ?string $title = null): Resume
    {
        $hash = hash_file('sha256', $file->getRealPath());

        if ($hash === false) {
            throw new RuntimeException('Unable to read the uploaded resume.');
        }

        $path = null;

        try {
            $resume = DB::transaction(function () use ($user, $file, $title, $hash, &$path) {
                // Serialize uploads for the same owner before writing any file.
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $existing = $user->resumes()->where('file_hash', $hash)->first();

                if ($existing !== null) {
                    return $existing;
                }

                $extension = $file->getMimeType() === 'application/pdf' ? 'pdf' : 'txt';
                $path = 'resumes/'.$user->getKey().'/'.Str::uuid().'.'.$extension;
                $stream = fopen($file->getRealPath(), 'rb');

                if ($stream === false) {
                    throw new RuntimeException('Unable to read the uploaded resume.');
                }

                try {
                    if (! Storage::disk('local')->put($path, $stream, ['visibility' => 'private'])) {
                        throw new RuntimeException('Unable to store the resume.');
                    }
                } finally {
                    fclose($stream);
                }

                return $user->resumes()->create([
                    'title' => $title ?? Str::limit(
                        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                        45,
                        ''
                    ),
                    'file_path' => $path,
                    'file_hash' => $hash,
                    'parsing_status' => Resume::PENDING,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        if ($resume->wasRecentlyCreated) {
            DB::afterCommit(function () use ($resume): void {
                try {
                    Bus::dispatch((new ProcessResume($resume->id))->onConnection(config('resumes.queue_connection')));
                } catch (Throwable $exception) {
                    $resume->newQuery()->whereKey($resume->id)->where('ai_run', 0)->where('parsing_status', 'pending')->update([
                        'parsing_status' => 'failed',
                        'ai_error_code' => 'queue_unavailable',
                    ]);
                    Log::warning('AI processing could not be queued.', [
                        'record_id' => $resume->id,
                        'exception_type' => $exception::class,
                    ]);
                }
            });
            $resume->refresh();
        }

        return $resume;
    }
}
