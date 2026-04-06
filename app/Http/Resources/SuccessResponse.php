<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SuccessResponse
{
    /**
     * Create a success response
     */
    public static function make(
        $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Create response with data collection
     */
    public static function collection(
        $data,
        string $message = 'Data retrieved successfully',
        ?array $pagination = null
    ): array {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($pagination) {
            $response['pagination'] = $pagination;
        }

        return $response;
    }

    /**
     * Create response for created resource (201)
     */
    public static function created(
        $data,
        string $message = 'Resource created successfully'
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Create response for updated resource
     */
    public static function updated(
        $data,
        string $message = 'Resource updated successfully'
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Create response for deleted resource
     */
    public static function deleted(string $message = 'Resource deleted successfully'): array
    {
        return [
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * Create paginated response
     */
    public static function paginated($items, $paginator, string $message = 'Data retrieved successfully'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
