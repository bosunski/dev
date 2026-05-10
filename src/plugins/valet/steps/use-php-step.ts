import type { Step } from '../../../types/step.js'
import type { Runner } from '../../../execution/runner.js'

export class UsePhpStep implements Step {
  constructor(private readonly phpVersion: string, private readonly valetBin: string) {}

  name(): string {
    return `Valet: use PHP ${this.phpVersion}`
  }

  id(): string {
    return `valet-use-php-${this.phpVersion}`
  }

  async done(_runner: Runner): Promise<boolean> {
    return false
  }

  async run(runner: Runner): Promise<boolean> {
    const formula = this.phpVersion === '8.3' ? 'php' : `php@${this.phpVersion}`
    return runner.exec([this.valetBin, 'use', formula, '--force'])
  }
}
