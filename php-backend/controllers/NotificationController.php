<?php
namespace LandLens\Controllers;

class NotificationController extends BaseController {

    public function index() {
        $this->json([
            [
                'id' => 'notif-01',
                'title' => 'Live Database Connected',
                'message' => 'LandLens PHP routing connected to Hostinger MySQL & NVIDIA AI.',
                'isRead' => true,
                'createdTime' => date('c', strtotime('-1 hour'))
            ],
            [
                'id' => 'notif-02',
                'title' => 'Verification Report Ready',
                'message' => 'AI Verification score generated for Vineyard Estate (92/100).',
                'isRead' => false,
                'createdTime' => date('c', strtotime('-20 minutes'))
            ]
        ]);
    }

    public function markAsRead(string $id) {
        $this->json([
            'id' => $id,
            'isRead' => true,
            'message' => "Notification {$id} marked as read."
        ]);
    }

    public function markAllAsRead() {
        $this->json([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }
}
