void main() {
  try {
    const String message = 'Hello, World!';
    print(message);
  } catch (e, stackTrace) {
    // Robust error handling for standard output operations
    print('An unexpected error occurred: $e');
    print(stackTrace);
  }
}