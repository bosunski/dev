import type { StepResolver, Step } from '../../../types/step.js'
import { PackageManager, packagesConfigSchema } from '../config/package-manager.js'
import { PackagesStep } from '../steps/packages-step.js'
import { UserException } from '../../../exceptions.js'

export class PackagesResolver implements StepResolver {
  resolve(args: unknown): Step {
    const result = packagesConfigSchema.safeParse(args)
    if (!result.success) throw new UserException(`Invalid packages configuration: ${result.error.message}`)
    const config = result.data
    const packages = Array.isArray(config) ? config : config.install
    const manager = Array.isArray(config) || !config.manager
      ? PackageManager.detect()
      : new PackageManager(config.manager)

    return new PackagesStep(manager, packages)
  }
}
