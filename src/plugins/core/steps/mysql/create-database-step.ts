import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'

export class CreateDatabaseStep extends BaseStep {
  static readonly Host = 'mysql.dev.local'
  static readonly User = 'root'

  constructor(private readonly databases: string | string[]) {
    super()
  }

  id(): string {
    const dbs = Array.isArray(this.databases) ? this.databases.join('-') : this.databases
    return `mysql-create-database-${dbs}`
  }

  name(): string { return 'Create MySQL database' }

  async run(runner: Runner): Promise<boolean> {
    const dbs = Array.isArray(this.databases) ? this.databases : [this.databases]
    const sql = dbs.map(db => `CREATE DATABASE IF NOT EXISTS \`${db}\`;`).join(' ')
    if (!await this.waitUntilReady()) return false
    if (!await runner.exec([
      'docker', 'exec', '-i', 'dev-mysql', 'mysql', `-u${CreateDatabaseStep.User}`, '-e', sql,
    ])) return false
    return this.waitUntilReady()
  }

  async done(runner: Runner): Promise<boolean> {
    const dbs = Array.isArray(this.databases) ? this.databases : [this.databases]
    const proc = Bun.spawnSync([
      'docker', 'exec', 'dev-mysql', 'mysql', `-u${CreateDatabaseStep.User}`, '-e', 'SHOW DATABASES;',
    ])
    const output = new TextDecoder().decode(proc.stdout)
    return proc.exitCode === 0 && dbs.every(db => output.split('\n').includes(db))
  }

  private async waitUntilReady(timeoutMs = 60_000): Promise<boolean> {
    const deadline = Date.now() + timeoutMs
    let consecutiveSuccesses = 0
    while (Date.now() < deadline) {
      const result = Bun.spawnSync([
        'docker', 'exec', 'dev-mysql', 'mysqladmin', 'ping', `-u${CreateDatabaseStep.User}`, '--silent',
      ], { stdout: 'ignore', stderr: 'ignore' })
      consecutiveSuccesses = result.exitCode === 0 ? consecutiveSuccesses + 1 : 0
      if (consecutiveSuccesses >= 3) return true
      await Bun.sleep(1000)
    }
    return false
  }
}
