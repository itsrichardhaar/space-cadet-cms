<?php
class RevisionsController {

    public function list(Request $req): void {
        Auth::requireRole('editor');
        $entityType = $req->get('entity_type');
        $entityId   = $req->getInt('entity_id');
        if (!$entityType || !$entityId) {
            Response::error('entity_type and entity_id are required', 400);
        }
        if (!in_array($entityType, ['page', 'collection_item'], true)) {
            Response::error('Invalid entity_type', 400);
        }
        $rows = Revision::listForEntity($entityType, $entityId, 20);
        Response::success($rows);
    }

    public function restore(Request $req, int $id): void {
        Auth::requireRole('editor');
        $rev = Revision::findById($id);
        if (!$rev) Response::notFound('Revision not found');

        $restored = Revision::restore($id);

        // Take a new snapshot so the restore itself is reversible
        Revision::snapshot($rev['entity_type'], (int)$rev['entity_id'], Auth::userId());

        AuditLog::write(
            Auth::userId(), 'restored', $rev['entity_type'], (int)$rev['entity_id'],
            ['revision_id' => $id, 'restored_to' => $rev['created_at']]
        );

        Response::success($restored);
    }
}
