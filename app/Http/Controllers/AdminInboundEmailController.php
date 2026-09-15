<?php

namespace App\Http\Controllers;

use Aws\S3\S3Client;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;

class AdminInboundEmailController extends Controller
{
    /**
     * Lista los correos crudos que SES ha guardado en el bucket de recepción -- para poder leer
     * códigos de confirmación (ej. el que pide Gmail al verificar una dirección de reenvío) o
     * depurar por qué un documento no se ingirió, sin entrar a la consola de S3 a mano.
     */
    public function index()
    {
        $bucket = config('services.ses.inbound_bucket');

        $objects = collect();

        if ($bucket) {
            $result = $this->s3Client()->listObjectsV2(['Bucket' => $bucket, 'MaxKeys' => 200]);
            $objects = collect($result['Contents'] ?? [])->sortByDesc('LastModified')->values();
            $objects = $this->withSubjects($bucket, $objects);
        }

        return view('admin.inbound-emails', compact('objects', 'bucket'));
    }

    /**
     * Baja un correo puntual de S3 y lo muestra parseado (encabezados + cuerpo de texto) -- mismo
     * parser que usa SesInboundWebhookController::handle(), así se ve igual que como lo procesa
     * la app. La clave del objeto viaja por query string (no por segmento de ruta) porque puede
     * traer barras "/" que romperían el routing normal.
     */
    public function show(Request $request)
    {
        $key = (string) $request->query('key', '');
        abort_if($key === '', 404);

        $bucket = config('services.ses.inbound_bucket');
        $raw = (string) $this->s3Client()->getObject(['Bucket' => $bucket, 'Key' => $key])['Body'];
        $email = (new MailMimeParser())->parse($raw, false);

        return view('admin.inbound-email-show', [
            'key' => $key,
            'from' => (string) $email->getHeaderValue(HeaderConsts::FROM),
            'to' => (string) $email->getHeaderValue(HeaderConsts::TO),
            'subject' => (string) $email->getHeaderValue(HeaderConsts::SUBJECT),
            'date' => (string) $email->getHeaderValue(HeaderConsts::DATE),
            'text' => $email->getTextContent() ?: strip_tags((string) $email->getHtmlContent()),
            'attachments' => collect($email->getAllAttachmentParts())
                ->map(fn ($attachment) => $attachment->getFilename())
                ->filter()
                ->values(),
        ]);
    }

    /**
     * Le agrega el "subject" a cada objeto de la lista, para que la tabla no muestre solo la
     * clave críptica que le puso SES -- baja únicamente los primeros 8 KB de cada correo (los
     * encabezados siempre caben ahí, sin necesidad de traer el cuerpo/adjuntos completos), y en
     * paralelo (no uno por uno) para que la pantalla no se ponga lenta con muchos correos.
     *
     * @param  string  $bucket
     * @param  Collection  $objects
     * @return Collection
     */
    private function withSubjects(string $bucket, Collection $objects): Collection
    {
        $s3 = $this->s3Client();

        $promises = $objects->mapWithKeys(fn ($object) => [
            $object['Key'] => $s3->getObjectAsync([
                'Bucket' => $bucket,
                'Key' => $object['Key'],
                'Range' => 'bytes=0-8191',
            ]),
        ]);

        $results = PromiseUtils::settle($promises->all())->wait();

        return $objects->map(function ($object) use ($results) {
            $result = $results[$object['Key']] ?? null;
            $object['Subject'] = $result && $result['state'] === 'fulfilled'
                ? $this->extractSubject((string) $result['value']['Body'])
                : '';

            return $object;
        });
    }

    /**
     * Saca el encabezado "Subject" de un bloque de texto crudo (que puede venir cortado a la
     * mitad si solo se bajaron los primeros bytes del correo) y lo decodifica si viene en
     * "encoded-word" (=?UTF-8?B?...?=), que es como Gmail/Outlook codifican tildes/ñ en el
     * asunto -- sin esto se vería el texto codificado tal cual, no el asunto legible.
     *
     * @param  string  $raw
     * @return string
     */
    private function extractSubject(string $raw): string
    {
        $headersBlock = preg_split("/\r?\n\r?\n/", $raw, 2)[0] ?? $raw;

        if (! preg_match('/^Subject:(.*(?:\n[ \t].*)*)/mi', $headersBlock, $matches)) {
            return '';
        }

        $subject = trim(preg_replace('/\r?\n[ \t]+/', ' ', $matches[1]));
        $decoded = mb_decode_mimeheader($subject);

        return $decoded !== '' ? $decoded : $subject;
    }

    private function s3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => config('services.ses.region'),
            'credentials' => [
                'key' => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
    }
}
