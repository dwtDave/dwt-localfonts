import { describe, it, expect } from 'vitest';
import { cn } from './utils';

describe('cn utility function', () => {
  it('should merge multiple class names', () => {
    const result = cn('class1', 'class2', 'class3');
    expect(result).toBe('class1 class2 class3');
  });

  it('should handle conditional classes', () => {
    const isActive = true;
    const result = cn('base', isActive && 'active');
    expect(result).toBe('base active');
  });

  it('should filter out falsy values', () => {
    const result = cn('class1', false, 'class2', null, undefined, 'class3');
    expect(result).toBe('class1 class2 class3');
  });

  it('should handle object syntax', () => {
    const result = cn({
      'class1': true,
      'class2': false,
      'class3': true,
    });
    expect(result).toBe('class1 class3');
  });

  it('should merge Tailwind classes correctly', () => {
    // twMerge should handle conflicting Tailwind classes
    const result = cn('px-4 py-2', 'px-6');
    expect(result).toBe('py-2 px-6');
  });

  it('should handle arrays of classes', () => {
    const result = cn(['class1', 'class2'], 'class3');
    expect(result).toBe('class1 class2 class3');
  });

  it('should handle empty inputs', () => {
    const result = cn();
    expect(result).toBe('');
  });

  it('should handle mixed inputs', () => {
    const result = cn(
      'base',
      { active: true, disabled: false },
      ['extra', 'classes'],
      'final'
    );
    expect(result).toBe('base active extra classes final');
  });

  it('should override conflicting Tailwind utilities', () => {
    // When using the same Tailwind utility with different values, last one wins
    const result = cn('bg-red-500', 'bg-blue-500');
    expect(result).toBe('bg-blue-500');
  });

  it('should handle complex Tailwind merging', () => {
    const result = cn('text-sm font-medium', 'text-lg');
    expect(result).toBe('font-medium text-lg');
  });
});