import type { Runner } from '../../../../execution/runner.js'
import { BaseStep } from '../../../../step/base-step.js'

export class StartContainerStep extends BaseStep {
  id(): string { return 'mysql-start-container' }
  name(): string { return 'Start MySQL container' }

  async run(runner: Runner): Promise<boolean> {
    // Silently clean up any existing container; ignore errors if it doesn't exist
    Bun.spawnSync(['sh', '-c', 'docker kill dev-mysql 2>/dev/null; docker rm dev-mysql -f 2>/dev/null'])
    const dataDir = runner.config.globalPath('mysql/data')
    const cmd = `docker run --rm -v ${dataDir}:/var/lib/mysql -l dev.orbstack.domains=mysql.dev.local --name dev-mysql -p 127.0.0.1:3306:3306 -e MYSQL_ALLOW_EMPTY_PASSWORD='yes' -d mysql:8.3.0 --max-allowed-packet=512M`
    return runner.exec(cmd)
  }

  async done(runner: Runner): Promise<boolean> {
    return runner.exec('docker ps | grep dev-mysql')
  }
}
