export interface IOInterface {
  writeln(message: string): void
  write(message: string): void
  info(message: string): void
  error(message: string): void
  dev(message: string): void
  stepStart(name: string): void
  stepEnd(name: string, ok: boolean): void
  text(
    label: string,
    placeholder?: string,
    defaultValue?: string,
    required?: boolean,
    validate?: ((v: string) => string | undefined) | null,
    hint?: string,
  ): Promise<string>
  password(
    label: string,
    placeholder?: string,
    required?: boolean,
    validate?: ((v: string) => string | undefined) | null,
    hint?: string,
  ): Promise<string>
}
