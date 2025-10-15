import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Switch } from './switch';

describe('Switch Component', () => {
  it('should render switch control', () => {
    render(<Switch aria-label="Toggle feature" />);
    expect(screen.getByRole('switch', { name: /toggle feature/i })).toBeInTheDocument();
  });

  it('should be unchecked by default', () => {
    render(<Switch aria-label="Toggle" />);
    expect(screen.getByRole('switch')).not.toBeChecked();
  });

  it('should be checked when checked prop is true', () => {
    render(<Switch checked aria-label="Toggle" />);
    expect(screen.getByRole('switch')).toBeChecked();
  });

  it('should call onCheckedChange when toggled', async () => {
    const handleChange = vi.fn();
    const user = userEvent.setup();

    render(<Switch onCheckedChange={handleChange} aria-label="Toggle" />);

    const switchControl = screen.getByRole('switch');
    await user.click(switchControl);

    expect(handleChange).toHaveBeenCalledWith(true);
  });

  it('should toggle from checked to unchecked', async () => {
    const handleChange = vi.fn();
    const user = userEvent.setup();

    render(<Switch checked onCheckedChange={handleChange} aria-label="Toggle" />);

    const switchControl = screen.getByRole('switch');
    await user.click(switchControl);

    expect(handleChange).toHaveBeenCalledWith(false);
  });

  it('should be disabled when disabled prop is true', () => {
    render(<Switch disabled aria-label="Toggle" />);

    const switchControl = screen.getByRole('switch');
    expect(switchControl).toBeDisabled();
  });

  it('should not call onCheckedChange when disabled', async () => {
    const handleChange = vi.fn();
    const user = userEvent.setup();

    render(<Switch disabled onCheckedChange={handleChange} aria-label="Toggle" />);

    const switchControl = screen.getByRole('switch');
    await user.click(switchControl);

    expect(handleChange).not.toHaveBeenCalled();
  });

  it('should apply custom className', () => {
    render(<Switch className="custom-switch" aria-label="Toggle" />);
    expect(screen.getByRole('switch')).toHaveClass('custom-switch');
  });

  it('should support keyboard interaction', async () => {
    const handleChange = vi.fn();
    const user = userEvent.setup();

    render(<Switch onCheckedChange={handleChange} aria-label="Toggle" />);

    const switchControl = screen.getByRole('switch');
    switchControl.focus();
    await user.keyboard(' '); // Space key

    expect(handleChange).toHaveBeenCalledWith(true);
  });

  it('should forward ref', () => {
    const ref = vi.fn();
    render(<Switch ref={ref} aria-label="Toggle" />);
    expect(ref).toHaveBeenCalled();
  });

  it('should accept id prop for label association', () => {
    render(
      <div>
        <label htmlFor="my-switch">Enable feature</label>
        <Switch id="my-switch" />
      </div>
    );

    const switchControl = screen.getByRole('switch', { name: /enable feature/i });
    expect(switchControl).toHaveAttribute('id', 'my-switch');
  });
});