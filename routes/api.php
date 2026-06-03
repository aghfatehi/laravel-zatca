<?php

use Aghfatehi\Zatca\Facades\Zatca;
use Aghfatehi\Zatca\Jobs\SyncInvoiceToZatcaJob;
use Illuminate\Support\Facades\Route;

Route::middleware(config('zatca.api_middleware', 'api'))->prefix('zatca')->group(function () {
    Route::post('onboard', function (\Illuminate\Http\Request $request) {
        $otp = $request->input('otp');
        $solutionName = $request->input('solution_name', 'ERP');

        if (!$otp) {
            return response()->json(['error' => 'OTP is required'], 422);
        }

        try {
            $egsUnit = config('zatca.egs', []);
            $result = \Aghfatehi\Zatca\Facades\Zatca::phase2()->generateKeysAndCsr($egsUnit, $solutionName);
            $compliance = \Aghfatehi\Zatca\Facades\Zatca::phase2()->issueComplianceCertificate($result['csr'], $otp);

            return response()->json([
                'success' => $compliance->success,
                'request_id' => $compliance->requestId,
                'binary_security_token' => $compliance->binarySecurityToken,
                'secret' => $compliance->secret,
                'error_message' => $compliance->errorMessage,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    Route::post('invoice/sync', function (\Illuminate\Http\Request $request) {
        $serial = $request->input('invoice_serial_number');

        if (!$serial) {
            return response()->json(['error' => 'invoice_serial_number is required'], 422);
        }

        try {
            SyncInvoiceToZatcaJob::dispatch(
                invoiceData: $request->all(),
                egsUnit: config('zatca.egs', []),
                certificate: config('zatca.certificate'),
                privateKey: config('zatca.private_key'),
                secret: config('zatca.secret'),
            );

            return response()->json(['message' => 'Job dispatched', 'serial' => $serial]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    Route::get('status', function () {
        return response()->json([
            'phase' => Zatca::phase()->value,
            'environment' => Zatca::environment()->value,
            'phase1_enabled' => Zatca::isPhase1Enabled(),
            'phase2_enabled' => Zatca::isPhase2Enabled(),
        ]);
    });
});
