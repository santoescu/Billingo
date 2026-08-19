<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Mostrar el formulario de creación de una nueva empresa.
     */
    public function create()
    {
        $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();
        $departments = Department::orderBy('descripcion')->get();

        return view('companies.create', compact('fiscalResponsibilities', 'departments'));
    }

    /**
     * Crear una nueva empresa y asignar al usuario actual como propietario.
     */
    public function store(Request $request)
    {
        $data = $this->validatedCompanyData($request);
        $data['status'] = 'active';

        if (filled($data['dian_certificate_password'] ?? null) && $request->hasFile('dian_certificate')) {
            $this->assertCertificatePasswordMatches(
                file_get_contents($request->file('dian_certificate')->getRealPath()),
                $data['dian_certificate_password'],
            );
        }

        $data = array_merge($data, $this->handleLogoUpload($request));

        $company = Company::create($data);

        $this->storeCertificateIfUploaded($request, $company);

        CompanyMember::create([
            'company_id' => (string) $company->_id,
            'user_id' => (string) $request->user()->_id,
            'role' => 'owner',
            'modules' => [],
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Company')]),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Actualizar una empresa (solo el 'owner' o un 'administrador' de
     * alguno de sus módulos).
     */
    public function update(Request $request, string $id)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $data = $this->validatedCompanyData($request);
        $data['status'] = $request->input('status', 'active');

        if (filled($data['dian_certificate_password'] ?? null) && $company->dian_certificate_content) {
            $this->assertCertificatePasswordMatches(
                $company->dian_certificate_content,
                $data['dian_certificate_password'],
            );
        }

        if ($request->boolean('remove_logo')) {
            $data['logo_data'] = null;
            $data['logo_mime'] = null;
        } else {
            $data = array_merge($data, $this->handleLogoUpload($request));
        }

        $company->update($data);

        $this->storeCertificateIfUploaded($request, $company);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Company')]),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Sube el certificado de firma digital de inmediato (arrastrar y soltar),
     * sin esperar a que se guarde el resto del formulario de la empresa.
     * Usa la misma verificación de acceso que update().
     */
    public function uploadCertificate(Request $request, string $id)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['p12', 'pfx'], true)) {
            return response()->json(['message' => __('The certificate must be a .p12 or .pfx file.')], 422);
        }

        $this->storeCertificateFile($company, $file);

        return response()->json([
            'message' => __('Certificate uploaded.'),
            'filename' => $company->fresh()->dian_certificate_filename,
        ]);
    }

    /**
     * Valida un certificado + clave que todavía no se ha guardado (formulario
     * de crear empresa): intenta abrir el .p12/.pfx con esa clave usando
     * OpenSSL, sin persistir nada todavía.
     */
    public function validateCertificate(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|max:5120',
            'certificate_password' => 'required|string',
        ]);

        $extension = strtolower($request->file('certificate')->getClientOriginalExtension());
        if (! in_array($extension, ['p12', 'pfx'], true)) {
            return response()->json(['valid' => false, 'message' => __('The certificate must be a .p12 or .pfx file.')], 422);
        }

        $content = file_get_contents($request->file('certificate')->getRealPath());

        return $this->pkcs12ValidationResponse($content, $request->input('certificate_password'));
    }

    /**
     * Valida la clave contra el certificado que ya está guardado para esta
     * empresa (formulario de editar empresa, cuando no se sube un archivo
     * nuevo, solo se comprueba la clave contra el que ya está).
     */
    public function validateExistingCertificate(Request $request, string $id)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $request->validate([
            'certificate_password' => 'required|string',
        ]);

        if (! $company->dian_certificate_content) {
            return response()->json(['valid' => false, 'message' => __('This company has no certificate uploaded yet.')], 422);
        }

        return $this->pkcs12ValidationResponse($company->dian_certificate_content, $request->input('certificate_password'));
    }

    private function pkcs12ValidationResponse(string $content, string $password)
    {
        $certs = [];
        $valid = @openssl_pkcs12_read($content, $certs, $password);

        return response()->json([
            'valid' => $valid,
            'message' => $valid ? __('The password matches the certificate.') : __('The password does not match this certificate.'),
        ]);
    }

    /**
     * Última línea de defensa del lado del servidor: aunque el botón
     * "Validar" del formulario ya lo haya comprobado, no se guarda ninguna
     * clave de certificado sin verificarla de nuevo aquí con OpenSSL.
     */
    private function assertCertificatePasswordMatches(string $certificateContent, string $password): void
    {
        $certs = [];

        if (! @openssl_pkcs12_read($certificateContent, $certs, $password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dian_certificate_password' => __('The password does not match this certificate.'),
            ]);
        }
    }

    /**
     * Elimina el certificado guardado (y su clave), dejando la empresa sin
     * certificado hasta que se suba uno nuevo.
     */
    public function destroyCertificate(Request $request, string $id)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $company->update([
            'dian_certificate_content' => null,
            'dian_certificate_password' => null,
            'dian_certificate_original_name' => null,
        ]);

        return response()->json(['message' => __('Certificate removed.')]);
    }

    /**
     * Genera un nuevo token de API para la empresa (invalida el anterior).
     * El valor en texto plano solo se devuelve en esta respuesta.
     */
    public function regenerateApiToken(Request $request, string $id)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $plainTextToken = $company->generateApiToken();

        return response()->json(['api_token' => $plainTextToken]);
    }

    /**
     * Seleccionar la empresa activa para trabajar en ella (guarda en sesión).
     */
    public function select(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
        ]);

        $company = Company::findOrFail($request->company_id);

        $membership = CompanyMember::where('company_id', $request->company_id)
            ->where('user_id', (string) $request->user()->_id)
            ->first();

        abort_unless($membership, 404);

        $isOwner = $membership->role === 'owner';

        if ($isOwner) {
            $myModules = collect($company->modules ?? [])->mapWithKeys(fn ($module) => [$module => 'owner'])->all();
        } else {
            $myModules = collect($membership->modules ?? [])
                ->filter(fn ($assignment) => $company->hasModule($assignment['module'] ?? ''))
                ->mapWithKeys(fn ($assignment) => [$assignment['module'] => $assignment['role']])
                ->all();
        }

        session([
            'selected_company' => [
                'id' => (string) $company->_id,
                'name' => $company->name,
                'identificacion' => $company->identificacion,
                'role' => $membership->role,
                'modules' => $myModules,
            ],
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Igual que ProductController::handleImageUpload(): la foto se guarda
     * en base64 directo en el documento de Mongo, nada se escribe en disco.
     *
     * @return array{logo_data?: string, logo_mime?: string} Vacío si no vino ningún logo nuevo.
     */
    private function handleLogoUpload(Request $request): array
    {
        if (! $request->hasFile('logo')) {
            return [];
        }

        $request->validate([
            'logo' => 'image|max:1024',
        ]);

        $file = $request->file('logo');

        return [
            'logo_data' => base64_encode(file_get_contents($file->getRealPath())),
            'logo_mime' => $file->getMimeType(),
        ];
    }

    private function validatedCompanyData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'identification_type' => 'nullable|string|in:11,12,13,21,22,31,41,42,47,48,50,91',
            'identificacion' => 'required|string|max:20',
            'dv' => 'nullable|string|max:1',
            'person_type' => 'nullable|string|in:1,2',
            'fiscal_responsibilities' => 'nullable|array',
            'fiscal_responsibilities.*' => 'string|max:20',
            'address' => 'nullable|string|max:255',
            'department_code' => 'nullable|string|max:10',
            'city_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'dian_pin' => 'nullable|string|max:20',
            'dian_software_id' => 'nullable|string|max:100',
            'dian_certificate' => 'nullable|file|max:5120',
            'dian_certificate_password' => 'nullable|string|max:255',
            'dian_environment' => 'nullable|string|in:1,2',
        ]);

        unset($data['dian_certificate']);
        if (blank($data['dian_certificate_password'] ?? null)) {
            unset($data['dian_certificate_password']);
        }

        $data['fiscal_responsibilities'] = implode(';', $data['fiscal_responsibilities'] ?? []);

        $data['dv'] = ($data['identification_type'] ?? null) === '31'
            ? Company::calculateVerificationDigit($data['identificacion'])
            : null;

        return $data;
    }

    /**
     * El certificado de firma digital (.p12/.pfx) es opcional en el
     * formulario de creación de la empresa: si no se sube uno, simplemente
     * no se guarda nada (se puede subir después desde la edición).
     */
    private function storeCertificateIfUploaded(Request $request, Company $company): void
    {
        if (! $request->hasFile('dian_certificate')) {
            return;
        }

        $file = $request->file('dian_certificate');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['p12', 'pfx'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dian_certificate' => __('The certificate must be a .p12 or .pfx file.'),
            ]);
        }

        $this->storeCertificateFile($company, $file, $request->input('dian_certificate_password'));
    }

    /**
     * Guarda el contenido del certificado directamente en el documento de la
     * empresa (cifrado, igual que la clave), en vez de en disco: así
     * sobrevive a despliegues que no persisten storage/ entre versiones. La
     * clave es opcional aquí porque la subida por arrastrar-y-soltar sube el
     * archivo solo, sin esperar a que se guarde el resto del formulario
     * (donde sí va la clave).
     */
    private function storeCertificateFile(Company $company, $file, ?string $password = null): void
    {
        $data = [
            'dian_certificate_content' => file_get_contents($file->getRealPath()),
            'dian_certificate_original_name' => $file->getClientOriginalName(),
        ];

        if ($password !== null) {
            $data['dian_certificate_password'] = $password;
        }

        $company->update($data);
    }
}
