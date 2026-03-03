import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { SkeletonLoader } from '@/components/SkeletonLoader';

describe('SkeletonLoader Component', () => {
  it('should render text variant', () => {
    const { container } = render(<SkeletonLoader variant="text" />);
    expect(container.querySelector('.animate-pulse')).toBeTruthy();
  });

  it('should render card variant', () => {
    const { container } = render(<SkeletonLoader variant="card" />);
    expect(container.querySelector('.rounded-lg')).toBeTruthy();
  });

  it('should render multiple skeletons when count is provided', () => {
    const { container } = render(<SkeletonLoader variant="card" count={3} />);
    const skeletons = container.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBe(3);
  });

  it('should render avatar variant', () => {
    const { container } = render(<SkeletonLoader variant="avatar" />);
    expect(container.querySelector('.rounded-full')).toBeTruthy();
  });

  it('should render button variant', () => {
    const { container } = render(<SkeletonLoader variant="button" />);
    expect(container.querySelector('.rounded-md')).toBeTruthy();
  });
});

describe('CardSkeleton Component', () => {
  it('should render card skeleton with proper structure', () => {
    const { container } = render(<SkeletonLoader variant="card" />);
    expect(container.querySelector('.rounded-lg')).toBeTruthy();
  });
});
