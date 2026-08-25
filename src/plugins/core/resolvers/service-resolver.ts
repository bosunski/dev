import type { Step, StepResolver } from '../../../types/step.js'
import { PackageManager } from '../config/package-manager.js'
import { serviceConfigSchema, ServiceManager } from '../config/service-manager.js'
import { ServiceStep } from '../steps/service-step.js'
import { UserException } from '../../../exceptions.js'

export class ServiceResolver implements StepResolver {
  resolve(args: unknown): Step {
    const result = serviceConfigSchema.safeParse(args)
    if (!result.success) throw new UserException(`Invalid service configuration: ${result.error.message}`)
    const manager = new ServiceManager(PackageManager.detect().name)
    return new ServiceStep(manager, result.data)
  }
}
