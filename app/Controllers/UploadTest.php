<?php

namespace App\Controllers;

class UploadTest extends BaseController
{
    public function index()
    {
        return view('upload_test');
    }

    public function doUpload()
    {
        $file = $this->request->getFile('testfile');
        if (!$file) {
            return 'No file received';
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return 'No file selected';
        }

        if (!$file->isValid()) {
            return 'Upload error: ' . $file->getErrorString();
        }

        if ($file->hasMoved()) {
            return 'File already moved';
        }

        // Use the actual assets directory to test permissions
        $targetDir = FCPATH . 'assets/settings';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $details = [
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'mimetype' => $file->getMimeType(),
            'clientMimeType' => $file->getClientMimeType(),
            'tempname' => $file->getTempName(),
            'extension' => $file->getExtension(),
            'targetDir' => $targetDir,
            'targetDirExists' => is_dir($targetDir) ? 'YES' : 'NO',
            'targetDirWritable' => is_writable($targetDir) ? 'YES' : 'NO',
            'writablePath' => WRITEPATH,
            'FCPATH' => FCPATH,
        ];

        $newName = 'test_' . time() . '.' . $file->getExtension();
        $movedOk = $file->move($targetDir, $newName);
        $details['moved'] = $movedOk ? 'SUCCESS' : 'FAILED';
        $details['finalPath'] = $targetDir . '/' . $newName;
        $details['finalFileExists'] = file_exists($details['finalPath']) ? 'YES' : 'NO';

        return '<pre>' . print_r($details, true) . '</pre>';
    }
}
