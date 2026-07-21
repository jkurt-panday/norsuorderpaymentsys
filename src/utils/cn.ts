type ClassValue = string | false | null | undefined | (string | false | null | undefined)[];

/**
 * Conditionally join class names together.
 * Supports strings, falsy values, and nested arrays.
 */
export function cn(...inputs: ClassValue[]): string {
  return inputs
    .flat(1)
    .filter(Boolean)
    .join(' ')
    .trim();
}