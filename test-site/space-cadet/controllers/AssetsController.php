<?php
class AssetsController {

    public function list(Request $req): void {
        Auth::requireRole('developer');
        Response::success(SiteAsset::all());
    }

    public function show(Request $req, int $id): void {
        Auth::requireRole('developer');
        Response::success(SiteAsset::findById($id) ?? Response::notFound());
    }

    public function create(Request $req): void {
        Auth::requireRole('developer');
        $body = $req->json() ?? [];
        $d = Validator::validate($body, ['name' => 'required|string', 'type' => 'required|string'])->failOrReturn();
        if (!in_array($d['type'], ['css', 'js'], true)) {
            Response::error('Type must be css or js', 422);
        }
        if (empty($body['slug'])) {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $d['name']));
            $d['slug'] = trim($base, '-');
        } else {
            $d['slug'] = preg_replace('/[^a-z0-9\-]/', '', strtolower($body['slug']));
        }
        $d['content'] = $body['content'] ?? '';
        $id = SiteAsset::create($d);
        AuditLog::write(Auth::userId(), 'created', 'asset', $id, ['name' => $d['name'], 'type' => $d['type']]);
        Response::created(SiteAsset::findById($id));
    }

    public function update(Request $req, int $id): void {
        Auth::requireRole('developer');
        SiteAsset::findById($id) ?? Response::notFound();
        $body = $req->json() ?? [];
        SiteAsset::update($id, $body);
        AuditLog::write(Auth::userId(), 'updated', 'asset', $id, []);
        Response::success(SiteAsset::findById($id));
    }

    public function delete(Request $req, int $id): void {
        Auth::requireRole('developer');
        $a = SiteAsset::findById($id) ?? Response::notFound();
        SiteAsset::delete($id);
        AuditLog::write(Auth::userId(), 'deleted', 'asset', $id, ['name' => $a['name']]);
        Response::noContent();
    }
}
