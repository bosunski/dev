import * as clack from '@clack/prompts'
import type { IOInterface } from '../types/io.js'

const isTTY = process.stdout.isTTY === true
const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏']
const GREEN = '\x1b[32m'
const RED = '\x1b[31m'
const DIM = '\x1b[2m'
const RESET = '\x1b[0m'
const CLEAR_LINE = '\x1b[2K\x1b[1G'

export class StdIO implements IOInterface {
  private spinnerName = ''
  private spinnerFrame = 0
  private spinnerTimer: ReturnType<typeof setInterval> | null = null

  // Output buffer for the current step — flushed only on failure
  private stepBuffer: string[] = []
  private stepBufferColor = 2

  startStepBuffer(color: number): void {
    this.stepBuffer = []
    this.stepBufferColor = color
  }

  appendStepBuffer(line: string): void {
    this.stepBuffer.push(line)
  }

  flushStepBuffer(): void {
    if (this.stepBuffer.length === 0) return
    const bar = `\x1b[38;5;${this.stepBufferColor}m│${RESET}`
    for (const line of this.stepBuffer) {
      process.stdout.write(`${bar} ${DIM}${line}${RESET}\n`)
    }
    this.stepBuffer = []
  }

  writeln(message: string): void {
    this.clearSpinner()
    console.log(message)
  }

  write(message: string): void {
    this.clearSpinner()
    process.stdout.write(message)
  }

  info(message: string): void {
    this.clearSpinner()
    console.log(`\x1b[34mℹ${RESET} ${message}`)
  }

  error(message: string): void {
    this.clearSpinner()
    console.error(`${RED}✖${RESET} ${message}`)
  }

  dev(message: string): void {
    this.clearSpinner()
    console.log(`${DIM}[dev]${RESET} ${message}`)
  }

  stepStart(name: string): void {
    this.spinnerName = name
    if (isTTY) {
      this.spinnerFrame = 0
      this.spinnerTimer = setInterval(() => {
        const frame = FRAMES[this.spinnerFrame % FRAMES.length]!
        process.stdout.write(`${CLEAR_LINE}${DIM}${frame}${RESET}  ${name}`)
        this.spinnerFrame++
      }, 80)
    } else {
      process.stdout.write(`  ${DIM}…${RESET}  ${name}\n`)
    }
  }

  stepEnd(_name: string, ok: boolean): void {
    const name = this.spinnerName
    this.clearSpinner()

    const mark = ok ? `${GREEN}✓${RESET}` : `${RED}✗${RESET}`
    process.stdout.write(`${mark}  ${name}\n`)

    if (!ok) {
      this.flushStepBuffer()
    } else {
      this.stepBuffer = []
    }

    this.spinnerName = ''
  }

  clearSpinner(): void {
    if (isTTY && this.spinnerTimer) {
      clearInterval(this.spinnerTimer)
      this.spinnerTimer = null
      process.stdout.write(CLEAR_LINE)
    }
  }

  async text(
    label: string,
    placeholder = '',
    defaultValue = '',
    required = true,
    _validate: ((v: string) => string | undefined) | null = null,
    hint = '',
  ): Promise<string> {
    this.clearSpinner()
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
    this.clearSpinner()
    const message = hint ? `${label}\n  ${DIM}${hint}${RESET}` : label
    const result = await clack.password({ message })
    if (clack.isCancel(result)) process.exit(1)
    return result
  }
}
