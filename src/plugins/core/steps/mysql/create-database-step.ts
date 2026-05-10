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
    const sql = dbs.map(db => `CREATE DATABASE IF NOT EXISTS ${db};`).join(' ')
    const cmd = `docker exec -i dev-mysql mysql -u${CreateDatabaseStep.User} -e "${sql}"`

    for (const delay of [1000, 2000, 3000]) {
      const ok = await runner.exec(cmd)
      if (ok) return true
      await Bun.sleep(delay)
    }
    return false
  }

  async done(runner: Runner): Promise<boolean> {
    const dbs = Array.isArray(this.databases) ? this.databases : [this.databases]
    const cmd = `docker exec dev-mysql mysql -u${CreateDatabaseStep.User} -e "SHOW DATABASES;"`
    const proc = Bun.spawnSync(['sh', '-c', cmd])
    const output = new TextDecoder().decode(proc.stdout)
    return dbs.every(db => output.includes(db))
  }
}
