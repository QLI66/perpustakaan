export interface ApiResponse<T> {
    success: boolean
    message: string
    data: T
}

export interface ApiError {
    message: string
    errors?: Record<string, string[]>
}
