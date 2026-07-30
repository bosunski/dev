import * as clack from '@clack/prompts'
import type { IOInterface } from '../types/io.js'

const GREEN = '\x1b[32m'
const RED = '\x1b[31m'
const DIM = '\x1b[2m'
const RESET = '\x1b[0m'

export class StdIO implements IOInterface {
  writeln(message: string): void {
    console.log(message)
  }

  write(message: string): void {
    process.stdout.write(message)
  }

  info(message: string): void {
    console.log(`\x1b[34mℹ${RESET} ${message}`)
  }

  error(message: string): void {
    console.error(`${RED}✖${RESET} ${message}`)
  }

  dev(message: string): void {
    console.log(`${DIM}[dev]${RESET} ${message}`)
  }

  stepStart(name: string): void {
    process.stdout.write(`${DIM}…${RESET}  ${name}\n`)
  }

  stepEnd(name: string, ok: boolean): void {
    const mark = ok ? `${GREEN}✓${RESET}` : `${RED}✗${RESET}`
    process.stdout.write(`${mark}  ${name}\n`)
  }

  async text(
    label: string,
    placeholder = '',
    defaultValue = '',
    required = true,
    _validate: ((v: string) => string | undefined) | null = null,
    hint = '',
  ): Promise<string> {
    const message = hint ? `${label}\n  ${DIM}${hint}${RESET}` : label
    const result = await clack.text({ message, placeholder, defaultValue, initialValue: defaultValue })
    if (clack.isCancel(result)) process.exit(1)
    return result
  }

  async password(
    label: string,
    placeholder = '',
    _required = true,
    _validate: ((v: string) => string | undefined) | null = null,
    hint = '',
  ): Promise<string> {
    const message = hint ? `${label}\n  ${DIM}${hint}${RESET}` : label
    const result = await clack.password({ message })
    if (clack.isCancel(result)) process.exit(1)
    return result
  }
}
